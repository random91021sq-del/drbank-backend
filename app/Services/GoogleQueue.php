<?php

namespace App\Services;

use Google\Cloud\PubSub\PubSubClient;

class GoogleQueue
{
    public static function getCredentials(): array
    {
        $credentialsPath = config('services.pubsub.credentials');

        if (! is_string($credentialsPath) || ! is_file($credentialsPath)) {
            throw new \RuntimeException(
                'No se encontró la credencial de Google Pub/Sub. Revisa PUBSUB_CREDENTIALS.'
            );
        }

        $credentials = json_decode(
            file_get_contents($credentialsPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return $credentials;
    }

    public static function sendQueue(mixed $data): void
    {
        $credentials = self::getCredentials();

        $pubSub = new PubSubClient([
            'projectId' => config('services.pubsub.project_id'),
            'credentials' => $credentials,
        ]);
        $topic = $pubSub->topic(config('services.pubsub.topic'));

        $topic->publish([
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }
}
