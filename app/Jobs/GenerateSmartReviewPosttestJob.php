<?php

namespace App\Jobs;

use App\Services\FirebaseNotificationService;
use App\Services\SmartReviewPosttestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateSmartReviewPosttestJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $idStudyBlock) {}

    public function handle(SmartReviewPosttestService $service): void
    {
        $result = $service->generate($this->idStudyBlock);

        if (! $result['created']) {
            return;
        }

        app(FirebaseNotificationService::class)->notifyClients(
            [$result['id_client']],
            'Evaluación de progreso disponible',
            'Tu evaluación de progreso ya está disponible. Ingresa para comprobar cuánto has mejorado.'
        );

        Log::info('SmartReview: notificación de postest enviada', [
            'id_study_block' => $this->idStudyBlock,
            'id_exam' => $result['id_exam'],
            'id_client' => $result['id_client'],
        ]);
    }
}
