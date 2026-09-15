<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Question;
use App\Services\FirebaseNotificationService;
use App\Services\NeuronAIServices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Enums\MessageRole;

class StudentRecommendationCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'student:recommendation';

    /**
     * @var string
     */
    protected $description = 'Genera recomendaciones de estudio con IA para cada examen registrado en el sistema';

    /**
     * Number of exam records processed per database query.
     */
    private const CHUNK_SIZE = 50;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando generación de recomendaciones por examen...');

        $total = Exam::whereNotNull('exam_summary','and')
            ->whereNull('recommendation')
            ->where('exam_summary', '!=', '[]')
            ->where('exam_summary', '!=', '')
            ->count();

        if ($total === 0) {
            $this->warn('No se encontraron exámenes pendientes de recomendación.');
            return self::FAILURE;
        }

        $this->info("Total de exámenes a procesar: {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $successCount  = 0;
        $skipCount     = 0;
        $errorCount    = 0;

        // Collect unique client IDs that receive at least one recommendation
        // so we can send a single batch notification at the end.
        $notifiedClients = [];

        Exam::whereNotNull('exam_summary','and')
            ->whereNull('recommendation')
            ->where('exam_summary', '!=', '[]')
            ->where('exam_summary', '!=', '')
            ->orderBy('id_exam')
            ->chunk(self::CHUNK_SIZE, function ($exams) use (
                $bar, &$successCount, &$skipCount, &$errorCount, &$notifiedClients
            ) {
                foreach ($exams as $exam) {
                    try {
                        // --------------------------------------------------------
                        // STEP 1: Decode the raw exam_summary JSON
                        // --------------------------------------------------------
                        $summary = json_decode($exam->getRawOriginal('exam_summary'), true);

                        if (empty($summary) || !is_array($summary)) {
                            $skipCount++;
                            $bar->advance();
                            continue;
                        }

                        // --------------------------------------------------------
                        // STEP 2: Enrich questions via DB join and merge with
                        //         the student's answers from the summary.
                        // --------------------------------------------------------
                        $enriched = $this->enrichSummary($summary);

                        if (empty($enriched)) {
                            $skipCount++;
                            $bar->advance();
                            continue;
                        }

                        // --------------------------------------------------------
                        // STEP 3: Build the prompt with all context embedded
                        // --------------------------------------------------------
                        $prompt = $this->buildPrompt($exam, $enriched);

                        // --------------------------------------------------------
                        // STEP 4: Call the AI agent (fresh instance per exam)
                        // --------------------------------------------------------
                        $agent = NeuronAIServices::make();

                        /** @var Message $response */
                        $response = $agent->chat(
                            Message::make(MessageRole::USER, $prompt)
                        );

                        $recommendation = $response->getContent();

                        if (empty($recommendation)) {
                            $errorCount++;
                            $bar->advance();
                            continue;
                        }

                        // --------------------------------------------------------
                        // STEP 5: Persist the recommendation back to this exam
                        // --------------------------------------------------------
                        Exam::where('id_exam','=',$exam->id_exam,'and')
                            ->update(['recommendation' => $recommendation]);

                        $notifiedClients[$exam->id_client] = true;
                        $successCount++;

                    } catch (\Throwable $e) {
                        $errorCount++;
                        Log::error('StudentRecommendation: error procesando examen', [
                            'id_exam'   => $exam->id_exam,
                            'id_client' => $exam->id_client,
                            'message'   => $e->getMessage(),
                            'trace'     => $e->getTraceAsString(),
                        ]);
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        // ----------------------------------------------------------------
        // STEP 6: Send Firebase notifications to all affected students.
        // ----------------------------------------------------------------
        $clientIds = array_keys($notifiedClients);

        if (!empty($clientIds)) {
            $this->info('Enviando notificaciones Firebase...');
            try {
                (new FirebaseNotificationService())->notifyClients(
                    $clientIds,
                    'Recomendaciones disponibles',
                    'Tus exámenes ahora incluyen recomendaciones personalizadas de mejora. ¡Revísalas!'
                );
                $this->info('Notificaciones enviadas correctamente.');
            } catch (\Throwable $e) {
                $this->warn('Error al enviar notificaciones Firebase: ' . $e->getMessage());
                Log::error('StudentRecommendation: error enviando notificaciones Firebase', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->table(
            ['Resultado', 'Total'],
            [
                ['Recomendaciones generadas', $successCount],
                ['Omitidos (sin datos)',       $skipCount],
                ['Errores',                    $errorCount],
                ['Estudiantes notificados',    count($clientIds)],
            ]
        );

        return self::SUCCESS;
    }

    // =========================================================================
    // Join question_ids from the exam summary against questions → themes →
    // specialties → areas, then merge the student's answer data.
    // =========================================================================
    private function enrichSummary(array $summary): array
    {
        $questionIds = array_unique(array_column($summary, 'question_id'));

        if (empty($questionIds)) {
            return [];
        }

        $questionsMap = Question::select([
                'questions.id_question',
                'questions.question',
                'questions.alt_a',
                'questions.alt_b',
                'questions.alt_c',
                'questions.alt_d',
                'questions.response      AS correct_answer',
                'questions.justification',
                'questions.distractor_analysis',
                'themes.theme',
                'specialties.specialty',
                'areas.area',
            ])
            ->join('themes',      'questions.id_theme',      '=', 'themes.id_theme')
            ->join('specialties', 'themes.id_specialty',     '=', 'specialties.id_specialty')
            ->join('areas',       'specialties.id_area',     '=', 'areas.id_area')
            ->whereIn('questions.id_question', $questionIds)
            ->get()
            ->keyBy('id_question');

        $enriched = [];

        foreach ($summary as $item) {
            $qid = $item['question_id'] ?? null;

            if (!$qid || !isset($questionsMap[$qid])) {
                continue;
            }

            $q             = $questionsMap[$qid];
            $studentAnswer = $item['response']       ?? null;
            $correctAnswer = $item['correct_answer'] ?? $q->correct_answer;

            $result = match(true) {
                $studentAnswer === null                                          => 'blank',
                strtoupper($studentAnswer) === strtoupper($correctAnswer ?? '') => 'correct',
                default                                                         => 'wrong',
            };

            $enriched[] = [
                'area'               => $q->area,
                'specialty'          => $q->specialty,
                'theme'              => $q->theme,
                'question'           => $q->question,
                'options'            => array_filter([
                    'A' => $q->alt_a,
                    'B' => $q->alt_b,
                    'C' => $q->alt_c,
                    'D' => $q->alt_d,
                ]),
                'correct_answer'     => $correctAnswer,
                'student_answer'     => $studentAnswer,
                'result'             => $result,
                'justification'      => $q->justification,
                'distractor_analysis'=> $q->distractor_analysis,
            ];
        }

        return $enriched;
    }

    // =========================================================================
    // Build the prompt with all exam and question context embedded inline.
    // =========================================================================
    private function buildPrompt(Exam $exam, array $enriched): string
    {
        $scoreLabel = $exam->score_percentage !== null
            ? number_format((float) $exam->score_percentage, 1) . '%'
            : 'N/A';

        $examTitle = $exam->title ?? "Examen #{$exam->id_exam}";

        $wrongItems   = array_filter($enriched, fn ($i) => $i['result'] === 'wrong');
        $blankItems   = array_filter($enriched, fn ($i) => $i['result'] === 'blank');
        $correctCount = count(array_filter($enriched, fn ($i) => $i['result'] === 'correct'));

        $failedBlock = '';
        $index       = 1;

        foreach (array_merge(array_values($wrongItems), array_values($blankItems)) as $item) {
            $answerLine = $item['result'] === 'wrong'
                ? "Respondió [{$item['student_answer']}] — correcto [{$item['correct_answer']}]"
                : 'No respondió (en blanco)';

            $justif     = $item['justification']       ? "  Justificación: {$item['justification']}"             : '';
            $distractor = $item['distractor_analysis'] ? "  Análisis de distractores: {$item['distractor_analysis']}" : '';

            $options = '';
            foreach ($item['options'] as $letter => $text) {
                $options .= "    {$letter}) {$text}\n";
            }

            $failedBlock .= <<<ITEM
            [{$index}] Área: {$item['area']} | Especialidad: {$item['specialty']} | Tema: {$item['theme']}
                Pregunta: {$item['question']}
            {$options}    {$answerLine}
            {$justif}
            {$distractor}

            ITEM;

            if (++$index > 15) {
                $remaining = count($wrongItems) + count($blankItems) - 15;
                if ($remaining > 0) {
                    $failedBlock .= "    ... y {$remaining} pregunta(s) adicional(es) incorrectas o en blanco.\n";
                }
                break;
            }
        }

        return <<<PROMPT
        Genera una recomendación de estudio personalizada basada en los siguientes datos del examen.

        === RESUMEN DEL EXAMEN ===
        Título      : {$examTitle}
        Estudiante  : ID {$exam->id_client}
        Puntaje     : {$scoreLabel}
        Correctas   : {$correctCount}
        Incorrectas : {$exam->incorrect_answers}
        En blanco   : {$exam->empty_answers}

        === PREGUNTAS INCORRECTAS O EN BLANCO ===
        {$failedBlock}
        Redacta una recomendación motivadora, clara y accionable basada exclusivamente en los datos anteriores.
        PROMPT;
    }
}
