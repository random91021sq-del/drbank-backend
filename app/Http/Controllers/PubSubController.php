<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Services\GoogleQueue;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

            Log::info('Envelope completo', ['envelope' => $envelope]);

            foreach (array_chunk($messages, 5) as $batch) {
                foreach ($batch as $message) {
                    $this->processPubSubMessage($message);
                }
            }

            return response()->json(['status' => 'OK'], 200);
        } catch (\Throwable $th) {
            Log::error('Error procesando mensaje', ['error' => $th->getMessage()]);

            return response()->json(['error' => 'Internal Server Error', 'details' => $th->getMessage()], 500);
        }
    }

    protected function processPubSubMessage(array $message): void
    {
        $pubsubData = $message['data'] ?? null;

        Log::info('PubSub data (raw)', ['data' => $pubsubData]);

        if (empty($pubsubData)) {
            throw new \RuntimeException('Bad Request: No data');
        }

        $decodedMessage = base64_decode($pubsubData, true);
        if ($decodedMessage === false) {
            throw new \RuntimeException('Bad Request: invalid base64 data');
        }

        Log::info('Mensaje decodificado', ['message' => $decodedMessage]);

        $notificationData = json_decode($decodedMessage, true);
        if (! is_array($notificationData)) {
            throw new \RuntimeException('Bad Request: invalid payload');
        }

        Log::info('Datos finales', ['data' => $notificationData]);

        switch ($notificationData['value']['type']) {
            case 1:
                $this->sendEmailActivationAccount($notificationData);
                break;
            case 2:
                $this->sendEmailRecoverPassword($notificationData);
                break;
            case 3:
                $this->sendEmailDownloadExamSummary($notificationData);
                break;
            case 4:
                $this->sendEmailSupport($notificationData);
                break;
            default:
                throw new \RuntimeException('Tipo de notificación no reconocido');
        }
    }

    public function sendEmailActivationAccount(mixed $data)
    {
        $email = null;

        $email = $data['value']['email'];
        $name = $data['value']['name'] ?? '';
        $last_name = $data['value']['last_name'] ?? '';
        $code_activate = $data['value']['code_activate'] ?? '';
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para activación de contraseña');
        }
        CustomResponse::sendEmail('es', $email, ['name' => $name, 'last_name' => $last_name, 'code_activate' => $code_activate], 1);
    }

    public function sendEmailRecoverPassword(mixed $data)
    {
        $email = null;

        $email = $data['value']['email'];
        $name = $data['value']['name'] ?? '';
        $last_name = $data['value']['last_name'] ?? '';
        $token = $data['value']['token'] ?? '';
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para recuperación de contraseña');
        }
        CustomResponse::sendEmail('es', $email, ['name' => $name, 'last_name' => $last_name, 'token' => $token], 2);
    }

    public function sendEmailDownloadExamSummary(mixed $data)
    {
        $email = null;
        $email = $data['value']['email'];
        $name = $data['value']['name'] ?? '';
        $exams = $data['value']['exams'];
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para descarga de resumen de examen');
        }
        CustomResponse::sendEmail('es', $email, ['exams' => $exams, 'name' => $name], 3);
    }

    public function sendEmailSupport(mixed $data){
        
        $email = null;
        $email = $data['value']['email'];
        $name = $data['value']['name'] ?? '';
        $reason = $data['value']['reason'] ?? '';
        $description = $data['value']['description'] ?? '';
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para soporte');
        }
        CustomResponse::sendEmail('es', $email, ['name' => $name, 'reason' => $reason, 'description' => $description], 4);
    }
}
