<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\History;
use App\Models\StudentStudyPlan;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StudentPlanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'student:plan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para la elaboración del plan de estudio del estudiante';

    /**
     * Execute the console command.
     */
   public function handle()
    {
        $this->info('🚀 Generando plan de estudio adaptativo por estudiante...');

        // PASO 2: Obtener estudiantes
        $students = Client::select('id_client')->get();
        $bar = $this->output->createProgressBar(count($students));
        $bar->start();

        foreach ($students as $student) {

            // ============================================================
            // PASO 3: Obtener únicamente el historial de este estudiante
            // ============================================================
            $performances = History::select([
                'themes.uuid',
                DB::raw('SUM(history.count) as total_questions'),
                DB::raw('SUM(history.ok) as correct_answers'),
                DB::raw('SUM(history.error) as wrong_answers'),
                DB::raw('SUM(history.empty) as blank_answers'),
                'themes.id_theme',
            ])
                ->join('themes', 'history.id_theme', '=', 'themes.id_theme')
                ->where('history.id_client', $student->id_client)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('questions')
                        ->whereColumn('questions.id_theme', 'history.id_theme');
                })
                ->groupBy('themes.id_theme', 'themes.uuid')
                ->get();

            // Load the newest available video once per theme. The URL stored in
            // theme_videos is included directly in the generated plan.
            $videoUrlsByTheme = DB::table('theme_videos')
                ->whereIn('id_theme', $performances->pluck('id_theme'))
                ->whereNotNull('url')
                ->where('url', '<>', '')
                ->orderByDesc('id_theme_videos')
                ->get(['id_theme', 'url'])
                ->unique('id_theme')
                ->pluck('url', 'id_theme');

            // ============================================================
            // PASO 4: Calcular el IP propio del estudiante
            // ============================================================
            $studentOwnTopics = [];
            foreach ($performances as $item) {
                $A = $item->correct_answers;
                $E = $item->wrong_answers;
                $N = $item->blank_answers;

                $numerador = ($E / 3.0) + ($N / 6.0);
                $denominador = $A + ($E / 3.0) + ($N / 6.0) + 1;
                $ip = $numerador / $denominador;

                if ($ip > 0) {
                    $studentOwnTopics[] = [
                        'theme_uuid' => $item->uuid,
                        'video_url' => $videoUrlsByTheme->get($item->id_theme),
                        'ip_score' => round($ip, 4),
                        'type' => ($ip >= 0.50) ? 'critical' : 'review',
                        'source' => 'self',
                    ];
                }
            }

            // Ordenar Self por prioridad (IP más alto primero)
            usort($studentOwnTopics, function ($a, $b) {
                return $b['ip_score'] <=> $a['ip_score'];
            });

            // ============================================================
            // PASO 5: DISTRIBUCIÓN POR DÍAS (solo temas del estudiante)
            // ============================================================

            $daysOfWeek = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
            $weeklySchedule = [];

            $nowPeru = Carbon::now('America/Lima');
            $startDate = $nowPeru->startOfWeek(Carbon::MONDAY);
            if ($nowPeru->dayOfWeek !== Carbon::MONDAY) {
                $startDate = $nowPeru->next(Carbon::MONDAY);
            }

            // Índice individual de los temas priorizados del estudiante
            $selfIndex = 0;
            $totalSelf = count($studentOwnTopics);

            // Si el estudiante aún no tiene historial, el plan queda vacío.
            if ($totalSelf === 0) {
                $planData = [
                    'daily_distribution' => 0,
                    'weekly_schedule' => ['lunes' => [], 'martes' => [], 'miércoles' => [], 'jueves' => [], 'viernes' => [], 'sábado' => []],
                    'sunday_review' => [],
                ];
                StudentStudyPlan::updateOrCreate(
                    ['id_client' => $student->id_client],
                    ['plan_data' => json_encode($planData), 'generated_at' => now()]
                );
                $bar->advance();
                continue;
            }

            // Recorremos los 6 días
            for ($dayIndex = 0; $dayIndex < 6; $dayIndex++) {
                $dailyList = [];
                $currentDate = $startDate->copy()->addDays($dayIndex);
                $dayName = $daysOfWeek[$dayIndex];

                // Siempre intentamos poner 4 temas
                for ($i = 0; $i < 4; $i++) {
                    $topicToAdd = null;

                    if ($selfIndex < $totalSelf) {
                        $topicToAdd = $studentOwnTopics[$selfIndex];
                        $selfIndex++;
                    }

                    // Si hay un tema para agregar, lo ponemos
                    if ($topicToAdd) {
                        $topicToAdd['assigned_date'] = $currentDate->toDateString();
                        $dailyList[] = $topicToAdd;
                    }
                }

                $weeklySchedule[$dayName] = $dailyList;
            }

            // ============================================================
            // PASO 6: Domingo de repaso
            // ============================================================
            // El domingo se repasan TODOS los temas de la semana (Self + Global)
            $allWeekTopics = [];
            foreach ($weeklySchedule as $day => $topics) {
                foreach ($topics as $topic) {
                    $allWeekTopics[] = $topic;
                }
            }
            $sundayReview = array_column($allWeekTopics, 'theme_uuid');

            // Guardar JSON
            $planData = [
                'daily_distribution' => 4,
                'weekly_schedule' => $weeklySchedule,
                'sunday_review' => $sundayReview,
            ];

            StudentStudyPlan::updateOrCreate(
                ['id_client' => $student->id_client],
                [
                    'plan_data' => json_encode($planData),
                    'generated_at' => $nowPeru,
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Plan semanal adaptativo generado para cada estudiante.');

        // ----------------------------------------------------------------
        // Send Firebase notifications to all students.
        // ----------------------------------------------------------------
        $this->info('Enviando notificaciones Firebase...');
        try {
            $clientIds = $students->pluck('id_client')->all();
            (new FirebaseNotificationService())->notifyClients(
                $clientIds,
                'Plan de estudio disponible',
                'Tu plan de estudio para esta semana ya está listo. ¡Revísalo y comienza a prepararte!'
            );
            $this->info('Notificaciones enviadas correctamente.');
        } catch (\Throwable $e) {
            $this->warn('Error al enviar notificaciones Firebase: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('StudentPlan: error enviando notificaciones Firebase', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
