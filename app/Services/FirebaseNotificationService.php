<?php

namespace App\Services;

use App\Models\ClientFirebases;
use Google_Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    /**
     * How many tokens to notify per iteration before re-using the same
     * access token. FCM access tokens are valid for ~1 hour, so this
     * limit is about keeping memory low, not token expiry.
     */
    private const BATCH_SIZE = 100;

    private string $accessToken;
    private string $projectId;

    public function __construct()
    {
        $credentials = GoogleQueue::getCredentials();

        if (!is_array($credentials)) {
            throw new \RuntimeException('No se pudieron obtener las credenciales de Firebase.');
        }

        $this->projectId = $credentials['project_id']
            ?? getenv('FIREBASE_PROJECT_ID')
            ?: throw new \RuntimeException('No se pudo determinar el project_id de Firebase.');

        $client = new Google_Client();
        $client->setApplicationName(config('app.name'));
        $client->setAuthConfig($credentials);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $tokenResponse  = $client->fetchAccessTokenWithAssertion();
        $this->accessToken = $tokenResponse['access_token']
            ?? throw new \RuntimeException('No se pudo obtener el token de acceso de Firebase.');
    }

    /**
     * Send a notification to every FCM token belonging to the given client IDs.
     *
     * Tokens are fetched in chunks of BATCH_SIZE to keep memory usage flat
     * when processing thousands of students.
     *
     * @param  int[]  $clientIds
     * @param  string $title
     * @param  string $body
     */
    public function notifyClients(array $clientIds, string $title, string $body): void
    {
        if (empty($clientIds)) {
            return;
        }

        // Process client IDs in chunks to avoid loading all tokens into memory.
        foreach (array_chunk($clientIds, self::BATCH_SIZE) as $chunk) {
            $tokens = ClientFirebases::whereIn('id_client', $chunk,'and',false)
                ->whereNotNull('token_firebase')
                ->where('token_firebase', '!=', '')
                ->pluck('token_firebase');

            foreach ($tokens as $token) {
                $this->sendToToken($token, $title, $body);
            }
        }
    }

    /**
     * Send a notification to a single FCM token.
     */
    private function sendToToken(string $token, string $title, string $body): void
    {
        $response = Http::withToken($this->accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token'        => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                ],
            ]);

        if (!$response->successful()) {
            Log::warning('FirebaseNotificationService: error enviando notificación', [
                'token'    => substr($token, 0, 20) . '...',
                'response' => $response->body(),
            ]);
        }
    }
}
