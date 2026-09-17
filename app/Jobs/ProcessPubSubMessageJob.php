<?php

namespace App\Jobs;

use App\Custom\CustomResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPubSubMessageJob implements ShouldQueue
{
    use Queueable;

    protected array $message;

    /**
     * Create a new job instance.
     */
    public function __construct(array $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $pubsubData = $this->message['data'] ?? null;

            if (empty($pubsubData)) {
                Log::warning('PubSub Job: No hay campo "data" en el mensaje', ['message' => $this->message]);

                return;
            }

            $decodedMessage = base64_decode($pubsubData, true);
            if ($decodedMessage === false) {
                Log::error('PubSub Job: Base64 inválido');

                return;
            }

            $notificationData = json_decode($decodedMessage, true);
            if (! is_array($notificationData) || ! isset($notificationData['value']['type'])) {
                Log::error('PubSub Job: Estrutura de JSON/payload inválida', ['decoded' => $decodedMessage]);

                return;
            }

            Log::info('Procesando mensaje en segundo plano', ['data' => $notificationData]);

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
                    Log::warning('PubSub Job: Tipo de notificación no reconocido', ['type' => $notificationData['value']['type']]);
            }
        } catch (\Throwable $th) {
            Log::error('Error procesando Job de PubSub', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            // Lanza la excepción si deseas que el worker reintente el Job según la configuración de Laravel
            throw $th;
        }
    }

    protected function sendEmailActivationAccount(array $data): void
    {
        $email = $data['value']['email'] ?? null;
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para activación');
        }

        CustomResponse::sendEmail('es', $email, [
            'name' => $data['value']['name'] ?? '',
            'last_name' => $data['value']['last_name'] ?? '',
            'code_activate' => $data['value']['code_activate'] ?? '',
        ], 1);
    }

    protected function sendEmailRecoverPassword(array $data): void
    {
        $email = $data['value']['email'] ?? null;
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para recuperación de contraseña');
        }

        CustomResponse::sendEmail('es', $email, [
            'name' => $data['value']['name'] ?? '',
            'last_name' => $data['value']['last_name'] ?? '',
            'token' => $data['value']['token'] ?? '',
        ], 2);
    }

    protected function sendEmailDownloadExamSummary(array $data): void
    {
        $email = $data['value']['email'] ?? null;
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para resumen de examen');
        }

        CustomResponse::sendEmail('es', $email, [
            'exams' => $data['value']['exams'] ?? [],
            'name' => $data['value']['name'] ?? '',
        ], 3);
    }

    protected function sendEmailSupport(array $data): void
    {
        $email = $data['value']['email'] ?? null;
        if (! $email) {
            throw new \RuntimeException('Email no encontrado para soporte');
        }

        CustomResponse::sendEmail('es', $email, [
            'name' => $data['value']['name'] ?? '',
            'reason' => $data['value']['reason'] ?? '',
            'description' => $data['value']['description'] ?? '',
        ], 4);
    }
}
