<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Jobs\ProcessPubSubMessageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PubSubController extends Controller
{
    public function pubSubEndpoint(Request $request)
    {
        $language = $request->query('lang');

        try {
            $envelope = $request->all();
            $messages = [];

            if (! empty($envelope['message'])) {
                $messages[] = $envelope['message'];
            } elseif (! empty($envelope['messages']) && is_array($envelope['messages'])) {
                $messages = $envelope['messages'];
            } else {
                return CustomResponse::responseMessage('pubSubMissingEnvelope',Response::HTTP_BAD_REQUEST,$language);
            }

            if (empty($messages)) {
                return CustomResponse::responseMessage('pubSubMissingData',Response::HTTP_BAD_REQUEST,$language);
            }

            Log::info('Envelope recibido de PubSub', ['count' => count($messages)]);

            // Encolar los trabajos para procesarlos en segundo plano
            foreach ($messages as $message) {
                ProcessPubSubMessageJob::dispatch($message);
            }

            // Responder inmediatamente a PubSub
            return CustomResponse::responseBody(['status' => 'OK']);

        } catch (\Throwable $th) {
            Log::error('Error recibiendo payload de PubSub', ['error' => $th->getMessage()]);

            return CustomResponse::responseMessage('pubSubServerError',Response::HTTP_INTERNAL_SERVER_ERROR,$language);
        }
    }
}
