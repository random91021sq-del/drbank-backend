<?php

namespace App\Jobs;

use App\Custom\CustomResponse;
use App\Services\FirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPubSubMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(protected array $message) {}

    public function handle(): void
    {
        try {
            $pubsubData = $this->message['data'] ?? null;

            if (empty($pubsubData)) {
                Log::warning('PubSub Job: No hay campo "data" en el mensaje');

                return;
            }

            $decodedMessage = base64_decode($pubsubData, true);
            $notificationData = $decodedMessage === false
                ? null
                : json_decode($decodedMessage, true);

            if (! is_array($notificationData) || ! isset($notificationData['value']['type'])) {
                Log::error('PubSub Job: Estructura de payload inválida');

                return;
            }

            match ((int) $notificationData['value']['type']) {
                1 => $this->sendEmailActivationAccount($notificationData),
                2 => $this->sendEmailRecoverPassword($notificationData),
                3 => $this->sendEmailDownloadExamSummary($notificationData),
                4 => $this->sendEmailSupport($notificationData),
                5 => $this->processSmartReviewPretest($notificationData),
                6 => $this->sendNewStudyBlockNotification($notificationData),
                default => Log::warning('PubSub Job: Tipo no reconocido', [
                    'type' => $notificationData['value']['type'],
                ]),
            };
        } catch (\Throwable $e) {
            Log::error('Error procesando Job de PubSub', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
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
            throw new \RuntimeException('Email no encontrado para recuperación');
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

    protected function sendNewStudyBlockNotification(array $data): void
    {
        $idClient = (int) ($data['value']['id_client'] ?? 0);

        if (! $idClient) {
            throw new \RuntimeException('PubSub SmartReview: No se encontró el cliente para la notificación.');
        }

        app(FirebaseNotificationService::class)->notifyClients(
            [$idClient],
            'Nuevo bloque de estudio disponible',
            'Felicidades, has activado la opción de crear un nuevo bloque de estudio.'
        );

        Log::info('SmartReview: notificación de nuevo bloque enviada', [
            'id_client' => $idClient,
            'id_study_block' => (int) ($data['value']['id_study_block'] ?? 0),
        ]);
    }

    protected function calculateSmartReviewQuality(bool $isCorrect, string $difficulty): int
    {
        $difficultyScore = match ($difficulty) {
            'hard' => 0,
            'regular' => 1,
            'easy' => 2,
            default => throw new \InvalidArgumentException("Dificultad inválida: {$difficulty}"),
        };

        return $difficultyScore + ($isCorrect ? 3 : 0);
    }

    protected function initializeSmartReviewTheme(
        int $idClient,
        int $idTheme,
        string $idExamType,
        int $quality,
        $now
    ): array {
        $easinessFactor = 2.5 + 0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02);

        return [
            'id_client' => $idClient,
            'id_theme' => $idTheme,
            'id_exam_type' => $idExamType,
            'repetitions' => $quality >= 3 ? 1 : 0,
            'interval_days' => 1,
            'easiness_factor' => round(max(1.3, $easinessFactor), 2),
            'last_quality' => $quality,
            'initialized_at' => $now,
            'last_reviewed_at' => null,
            'next_review_at' => $now->copy()->addDay(),
            'updated_at' => $now,
        ];
    }

    protected function processSmartReviewPretest(array $data): void
    {
        $idClient = (int) ($data['value']['id_client'] ?? 0);
        $idStudyBlock = (int) ($data['value']['id_study_block'] ?? 0);
        $idExam = (int) ($data['value']['id_exam'] ?? 0);

        if (! $idClient || ! $idStudyBlock || ! $idExam) {
            throw new \RuntimeException('PubSub SmartReview: Payload incompleto.');
        }

        $context = DB::table('exams as e')
            ->join('study_blocks as sb', 'sb.id_study_block', '=', 'e.id_study_block')
            ->join('study_block_themes as sbt', 'sbt.id_study_block', '=', 'sb.id_study_block')
            ->where('e.id_exam', $idExam)
            ->where('e.id_client', $idClient)
            ->where('e.id_study_block', $idStudyBlock)
            ->where('e.smart_review_stage', 'pretest')
            ->where('sb.id_client', $idClient)
            ->select('e.exam_summary', 'sbt.id_theme', 'sbt.id_exam_type')
            ->get();

        if ($context->isEmpty()) {
            throw new \RuntimeException('PubSub SmartReview: No se encontró el bloque o pretest.');
        }

        $themeIds = $context->pluck('id_theme')->map(fn ($id) => (int) $id)->unique()->values();
        $pairCount = $context->map(fn ($row) => $row->id_theme.'|'.$row->id_exam_type)
            ->unique()
            ->count();
        if ($pairCount < 4 || $pairCount > 20) {
            throw new \RuntimeException('PubSub SmartReview: El bloque debe contener entre 4 y 20 temas.');
        }
        $allowedPairs = $context->mapWithKeys(fn ($row) => [
            $row->id_theme.'|'.$row->id_exam_type => true,
        ]);

        $examSummary = json_decode($context->first()->exam_summary, true);
        if (! is_array($examSummary) || count($examSummary) !== 50) {
            throw new \RuntimeException('PubSub SmartReview: El pretest debe contener 50 preguntas.');
        }

        $questionIds = collect($examSummary)
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $questions = DB::table('questions')
            ->whereIn('id_question', $questionIds)
            ->whereIn('id_theme', $themeIds)
            ->select('id_question', 'id_theme', 'id_exam_type', 'response')
            ->get()
            ->keyBy('id_question');

        if ($questions->count() !== 50) {
            throw new \RuntimeException('PubSub SmartReview: Existen preguntas inválidas para el bloque.');
        }

        $correctAnswers = 0;
        $qualitiesByTheme = [];

        foreach ($examSummary as &$answer) {
            $idQuestion = (int) ($answer['question_id'] ?? 0);
            $question = $questions->get($idQuestion);
            $difficulty = strtolower(trim((string) ($answer['difficulty'] ?? '')));

            if (! $question) {
                throw new \RuntimeException("PubSub SmartReview: No existe la pregunta {$idQuestion}.");
            }
            if (! $allowedPairs->has($question->id_theme.'|'.$question->id_exam_type)) {
                throw new \RuntimeException("PubSub SmartReview: La pregunta {$idQuestion} no pertenece al tema y tipo de examen del bloque.");
            }

            $studentAnswer = strtoupper(trim((string) ($answer['response'] ?? '')));
            $correctAnswer = strtoupper(trim((string) $question->response));
            $isCorrect = $studentAnswer === $correctAnswer;
            $quality = $this->calculateSmartReviewQuality($isCorrect, $difficulty);

            $answer['response'] = $studentAnswer;
            $answer['correct'] = $isCorrect;
            $pairKey = $question->id_theme.'|'.$question->id_exam_type;
            $qualitiesByTheme[$pairKey]['id_theme'] = (int) $question->id_theme;
            $qualitiesByTheme[$pairKey]['id_exam_type'] = (string) $question->id_exam_type;
            $qualitiesByTheme[$pairKey]['qualities'][] = $quality;

            if ($isCorrect) {
                $correctAnswers++;
            }
        }
        unset($answer);

        DB::transaction(function () use (
            $idClient,
            $idExam,
            $idStudyBlock,
            $examSummary,
            $correctAnswers,
            $qualitiesByTheme
        ) {
            $now = now('America/Lima');

            DB::table('exams')
                ->where('id_exam', $idExam)
                ->where('id_client', $idClient)
                ->update([
                    'score_percentage' => round(($correctAnswers / 50) * 100, 2),
                    'exam_summary' => json_encode($examSummary, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);

            foreach ($qualitiesByTheme as $themeResult) {
                $idTheme = $themeResult['id_theme'];
                $idExamType = $themeResult['id_exam_type'];
                $quality = max(0, min(5, (int) floor(collect($themeResult['qualities'])->avg())));

                DB::table('student_theme_reviews')->updateOrInsert(
                    [
                        'id_client' => $idClient,
                        'id_theme' => $idTheme,
                        'id_exam_type' => $idExamType,
                    ],
                    $this->initializeSmartReviewTheme(
                        $idClient,
                        $idTheme,
                        $idExamType,
                        $quality,
                        $now
                    )
                );

                Log::info('SmartReview: tema inicializado', [
                    'id_client' => $idClient,
                    'id_study_block' => $idStudyBlock,
                    'id_exam' => $idExam,
                    'id_theme' => $idTheme,
                    'id_exam_type' => $idExamType,
                    'quality_final' => $quality,
                ]);
            }
        });

        Log::info('SmartReview: pretest procesado correctamente por temas', [
            'id_client' => $idClient,
            'id_study_block' => $idStudyBlock,
            'id_exam' => $idExam,
            'total_themes' => count($qualitiesByTheme),
        ]);
    }
}
