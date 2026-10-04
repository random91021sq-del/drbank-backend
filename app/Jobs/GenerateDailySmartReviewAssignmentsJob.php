<?php

namespace App\Jobs;

use App\Services\FirebaseNotificationService;
use App\Services\SmartReviewAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateDailySmartReviewAssignmentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function handle(SmartReviewAssignmentService $service): void
    {
        $result = $service->generate();

        Log::info('SmartReview: asignaciones diarias generadas', $result);

        if (empty($result['client_ids'])) {
            return;
        }

        app(FirebaseNotificationService::class)->notifyClients(
            $result['client_ids'],
            'Repaso diario disponible',
            'Tienes nuevas actividades de repaso disponibles. Ingresa y continúa fortaleciendo los temas que estás estudiando.'
        );

        Log::info('SmartReview: notificación de asignaciones diarias enviada', [
            'client_ids' => $result['client_ids'],
        ]);
    }
}
