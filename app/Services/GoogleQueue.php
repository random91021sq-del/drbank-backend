<?php

namespace App\Services;

use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Http\Response;

class GoogleQueue
{
    public static function getCredentials()
    {
        try {
            $credentials = json_decode(
                file_get_contents(storage_path('app/google/avance-4-ec9701af2633.json')),
                true
            );
            return $credentials;
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public static function sendQueue(mixed $data)
    {
        $credentials = self::getCredentials();

        $pubSub = new PubSubClient([
            'projectId' => 'avance-4',
            'credentials' => $credentials,
        ]);
        $topic = $pubSub->topic('drbank_topic');
        
        //Convertimos el array/objeto a una cadena JSON
        $jsonData = json_encode($data);

        $topic->publish([
                'data' => $jsonData
        ]);
    }
}
