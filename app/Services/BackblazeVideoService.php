<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BackblazeVideoService
{
    private ?array $authorization = null;

    private array $bucketIds = [];

    public function temporaryUrl(?string $storedUrl): ?string
    {
        if (! $storedUrl) {
            return null;
        }

        try {
            [$bucketName, $fileName] = $this->parseDownloadUrl($storedUrl);
            $ttl = max(60, min((int) config('services.backblaze.signed_url_ttl', 3600), 604800));
            $cacheKey = 'backblaze:video-url:'.hash('sha256', $bucketName.'|'.$fileName);

            return Cache::remember($cacheKey, max(1, $ttl - 60), function () use ($storedUrl, $bucketName, $fileName, $ttl) {
                $authorization = $this->authorizeAccount();
                $bucketId = $this->resolveBucketId($authorization, $bucketName);
                $storageApi = $authorization['apiInfo']['storageApi'];

                $response = Http::withHeaders([
                    'Authorization' => $authorization['authorizationToken'],
                ])
                    ->post($storageApi['apiUrl'].'/b2api/v4/b2_get_download_authorization', [
                        'bucketId' => $bucketId,
                        'fileNamePrefix' => $fileName,
                        'validDurationInSeconds' => $ttl,
                    ])
                    ->throw();

                $downloadToken = $response->json('authorizationToken');
                if (! is_string($downloadToken) || $downloadToken === '') {
                    throw new RuntimeException('Backblaze no devolvió un token de descarga.');
                }

                return $storedUrl.(str_contains($storedUrl, '?') ? '&' : '?')
                    .'Authorization='.rawurlencode($downloadToken);
            });
        } catch (\Throwable $exception) {
            Log::error('No fue posible autorizar el video de Backblaze.', [
                'url' => $storedUrl,
                'message' => $exception->getMessage(),
            ]);

            // Preserve the database value when signing is temporarily unavailable.
            // This makes the configuration problem visible without pretending that
            // a theme has no associated video.
            return $storedUrl;
        }
    }

    /**
     * Sign several private video URLs with one Backblaze download token.
     *
     * @param  array<int, string|null>  $storedUrls
     * @return array<string, string>
     */
    public function temporaryUrls(array $storedUrls): array
    {
        $parsedUrls = [];

        foreach (array_unique(array_filter($storedUrls, fn ($url) => is_string($url) && $url !== '')) as $storedUrl) {
            try {
                [$bucketName, $fileName] = $this->parseDownloadUrl($storedUrl);
                $parsedUrls[$storedUrl] = compact('bucketName', 'fileName');
            } catch (\Throwable $exception) {
                Log::warning('La URL del video no se pudo preparar para Backblaze.', [
                    'url' => $storedUrl,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($parsedUrls === []) {
            return [];
        }

        try {
            $ttl = max(60, min((int) config('services.backblaze.signed_url_ttl', 3600), 604800));
            $bucketName = reset($parsedUrls)['bucketName'];
            $fileNamePrefix = $this->commonDirectoryPrefix(array_column($parsedUrls, 'fileName'));
            $cacheKey = 'backblaze:download-token:'.hash('sha256', $bucketName.'|'.$fileNamePrefix.'|'.$ttl);

            $downloadToken = Cache::remember($cacheKey, max(1, $ttl - 60), function () use ($bucketName, $fileNamePrefix, $ttl) {
                $authorization = $this->authorizeAccount();
                $bucketId = $this->resolveBucketId($authorization, $bucketName);
                $storageApi = $authorization['apiInfo']['storageApi'];

                $response = Http::withHeaders([
                    'Authorization' => $authorization['authorizationToken'],
                ])->post($storageApi['apiUrl'].'/b2api/v4/b2_get_download_authorization', [
                    'bucketId' => $bucketId,
                    'fileNamePrefix' => $fileNamePrefix,
                    'validDurationInSeconds' => $ttl,
                ])->throw();

                $token = $response->json('authorizationToken');
                if (! is_string($token) || $token === '') {
                    throw new RuntimeException('Backblaze no devolvió un token de descarga.');
                }

                return $token;
            });

            return collect(array_keys($parsedUrls))->mapWithKeys(fn ($storedUrl) => [
                $storedUrl => $storedUrl.(str_contains($storedUrl, '?') ? '&' : '?')
                    .'Authorization='.rawurlencode($downloadToken),
            ])->all();
        } catch (\Throwable $exception) {
            Log::error('No fue posible autorizar los videos de Backblaze.', [
                'message' => $exception->getMessage(),
            ]);

            return collect(array_keys($parsedUrls))->mapWithKeys(fn ($storedUrl) => [$storedUrl => $storedUrl])->all();
        }
    }

    private function authorizeAccount(): array
    {
        if ($this->authorization !== null) {
            return $this->authorization;
        }

        $keyId = config('services.backblaze.key_id');
        $applicationKey = config('services.backblaze.application_key');

        if (! is_string($keyId) || $keyId === '' || ! is_string($applicationKey) || $applicationKey === '') {
            throw new RuntimeException('Faltan las credenciales de Backblaze.');
        }

        $this->authorization = Http::withBasicAuth($keyId, $applicationKey)
            ->get('https://api.backblazeb2.com/b2api/v4/b2_authorize_account')
            ->throw()
            ->json();

        return $this->authorization;
    }

    private function resolveBucketId(array $authorization, string $bucketName): string
    {
        $configuredBucketId = config('services.backblaze.bucket_id');
        if (is_string($configuredBucketId) && $configuredBucketId !== '') {
            return $configuredBucketId;
        }

        if (isset($this->bucketIds[$bucketName])) {
            return $this->bucketIds[$bucketName];
        }

        $allowedBuckets = $authorization['apiInfo']['storageApi']['allowed']['buckets'] ?? [];

        foreach ($allowedBuckets as $bucket) {
            if (($bucket['name'] ?? null) === $bucketName && ! empty($bucket['id'])) {
                return $this->bucketIds[$bucketName] = $bucket['id'];
            }
        }

        $response = Http::withHeaders([
            'Authorization' => $authorization['authorizationToken'],
        ])
            ->post($authorization['apiInfo']['storageApi']['apiUrl'].'/b2api/v4/b2_list_buckets', [
                'accountId' => $authorization['accountId'],
                'bucketName' => $bucketName,
            ])
            ->throw();

        $bucketId = $response->json('buckets.0.bucketId');
        if (! is_string($bucketId) || $bucketId === '') {
            throw new RuntimeException("No se encontró el bucket {$bucketName} en Backblaze.");
        }

        return $this->bucketIds[$bucketName] = $bucketId;
    }

    private function parseDownloadUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('#^/file/([^/]+)/(.+)$#', $path, $matches)) {
            throw new RuntimeException('La URL del video no tiene un formato válido de Backblaze.');
        }

        $bucketName = rawurldecode($matches[1]);
        $fileName = rawurldecode($matches[2]);
        $configuredBucket = config('services.backblaze.bucket');

        if (is_string($configuredBucket) && $configuredBucket !== '' && $bucketName !== $configuredBucket) {
            throw new RuntimeException('El video no pertenece al bucket configurado.');
        }

        return [$bucketName, $fileName];
    }

    /** @param array<int, string> $fileNames */
    private function commonDirectoryPrefix(array $fileNames): string
    {
        $directories = array_map(fn ($fileName) => str_contains($fileName, '/')
            ? substr($fileName, 0, strrpos($fileName, '/') + 1)
            : '', $fileNames);

        $prefix = array_shift($directories) ?? '';
        foreach ($directories as $directory) {
            while ($prefix !== '' && ! str_starts_with($directory, $prefix)) {
                $prefix = substr($prefix, 0, max(0, strrpos(rtrim($prefix, '/'), '/') + 1));
            }
        }

        return $prefix;
    }
}
