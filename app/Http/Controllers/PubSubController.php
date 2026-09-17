<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPubSubMessageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PubSubController extends Controller
{
    public function pubSubEndpoint(Request $request)
    {
       try {
            $envelope = $request->all();
            $messages = [];

            if (! empty($envelope['message'])) {
                $messages[] = $envelope['message'];
            } elseif (! empty($envelope['messages']) && is_array($envelope['messages'])) {
                $messages = $envelope['messages'];
            } else {
                return response()->json(['error' => 'Bad Request: No envelope'], 400);
            }

            if (empty($messages)) {
                return response()->json(['error' => 'Bad Request: No data'], 400);
            }

            Log::info('Envelope recibido de PubSub', ['count' => count($messages)]);

            // Encolar los trabajos para procesarlos en segundo plano
            foreach ($messages as $message) {
                ProcessPubSubMessageJob::dispatch($message);
            }

            // Responder inmediatamente a PubSub
            return response()->json(['status' => 'OK'], 200);

        } catch (\Throwable $th) {
            Log::error('Error recibiendo payload de PubSub', ['error' => $th->getMessage()]);

            return response()->json([
                'error' => 'Internal Server Error',
                'details' => $th->getMessage()
            ], 500);
        }
    }
}