<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Models\StudentDailyProgress;
use App\Models\StudentStudyPlan;
use App\Models\Theme;
use App\Services\BackblazeVideoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentProgressController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/student/mark-topic-studied",
     *     summary="Marcar un tema como estudiado y actualizar porcentaje",
     *     tags={"Student Progress"},
     *     description="Registra que el estudiante ha estudiado un tema específico del plan del día y actualiza su porcentaje de avance semanal.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma de la respuesta (es, en, etc.)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="UUID del tema que el estudiante ha estudiado",
     *
     *         @OA\JsonContent(
     *             required={"theme_uuid"},
     *
     *             @OA\Property(
     *                 property="theme_uuid",
     *                 type="string",
     *                 example="550e8400-e29b-41d4-a716-446655440000",
     *                 description="UUID del tema a marcar como completado"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Tema marcado como estudiado exitosamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Progreso del estudiante guardado exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="theme_uuid", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="study_date", type="string", example="2026-08-08"),
     *                 @OA\Property(property="due_date", type="string", example="2026-08-08"),
     *                 @OA\Property(property="status", type="string", example="completed"),
     *                 @OA\Property(property="weekly_progress_percentage", type="integer", example=45),
     *                 @OA\Property(property="total_week_topics", type="integer", example=24),
     *                 @OA\Property(property="completed_week_topics", type="integer", example=11)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token no válido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No se encontró el plan o el tema",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="No tienes un plan de estudio activo")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Conflicto: Ya marcó el tema hoy",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Ya marcaste este tema como estudiado hoy")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el límite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el límite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Ocurrió un error al guardar el progreso: ...")
     *         )
     *     )
     * )
     */
    public function markAsStudied(Request $request)
    {
        Log::info('markAsStudied called', ['request' => $request->all()]);

        $language = $request->query('lang', 'es');

        $request->validate([
            'theme_uuid' => 'required|string',
        ]);

        $studentId = auth('sanctum')->user()->id_client;

        $nowPeru = Carbon::now('America/Lima');

        $today = $nowPeru->toDateString();

        // Inicio y fin de la semana actual
        $weekStart = $nowPeru->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $nowPeru->copy()->endOfWeek(Carbon::SUNDAY);

        /*
         * 1. Obtener el plan de estudio actual
         */
        $studyPlan = StudentStudyPlan::where('id_client', $studentId)
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();

        $planData = json_decode($studyPlan->plan_data, true);

        $weeklySchedule = $planData['weekly_schedule'] ?? [];

        /*
         * 2. Buscar el tema dentro de TODA la semana
         *
         * Ya no buscamos únicamente en el día actual.
         *
         * Esto permite recuperar temas atrasados.
         */
        $selectedTopic = null;

        foreach ($weeklySchedule as $day => $topics) {
            foreach ($topics as $topic) {
                if (($topic['theme_uuid'] ?? null) !== $request->theme_uuid) {
                    continue;
                }

                if (! isset($topic['assigned_date'])) {
                    continue;
                }

                $assignedDate = Carbon::parse(
                    $topic['assigned_date']
                )->startOfDay();
                /*
                 * Verificar que el tema pertenezca
                 * a la semana actual.
                 */
                if (
                    $assignedDate->greaterThanOrEqualTo(
                        $weekStart->copy()->startOfDay()
                    ) &&
                    $assignedDate->lessThanOrEqualTo(
                        $weekEnd->copy()->endOfDay()
                    )
                ) {
                    $selectedTopic = $topic;

                    break 2;
                }
            }
        }

        /*
         * 3. El tema debe pertenecer al plan de esta semana.
         */
        if (! $selectedTopic) {
            return response()->json([
                'error' => 'Este tema no pertenece al plan de estudio de esta semana.',
            ], 404);
        }

        /*
         * 4. Obtener la fecha en la que estaba asignado.
         */
        $dueDate = Carbon::parse(
            $selectedTopic['assigned_date']
        )->startOfDay();

        /*
         * 5. No permitir temas futuros.
         *
         * Sí permitimos cualquier tema atrasado.
         */
        if ($dueDate->greaterThan($nowPeru->copy()->startOfDay())) {
            return response()->json([
                'error' => 'Este tema todavía no está disponible para ser marcado como estudiado.',
            ], 422);
        }

        /*
         * 6. Verificar si ya fue completado esta semana.
         *
         * No usamos únicamente study_date porque:
         *
         * Ejemplo:
         *
         * Tema asignado lunes
         * Lo estudia miércoles
         *
         * study_date = miércoles
         * due_date   = lunes
         *
         * Queremos evitar que vuelva a marcarse esta semana.
         */
        $alreadyMarked = StudentDailyProgress::where(
            'id_client',
            $studentId
        )
            ->where('theme_uuid', $request->theme_uuid)
            ->whereBetween('due_date', [
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
            ])
            ->where('status', 'completed')
            ->exists();

        if ($alreadyMarked) {
            return response()->json([
                'error' => 'Ya marcaste este tema como estudiado esta semana.',
            ], 409);
        }

        DB::beginTransaction();

        try {

            /*
             * 7. Obtener todos los temas de la semana
             */
            $weekUuids = [];

            foreach ($weeklySchedule as $topics) {

                foreach ($topics as $topic) {

                    if (
                        ! isset($topic['theme_uuid']) ||
                        ! isset($topic['assigned_date'])
                    ) {
                        continue;
                    }

                    $assignedDate = Carbon::parse(
                        $topic['assigned_date']
                    )->startOfDay();

                    if (
                        $assignedDate->greaterThanOrEqualTo(
                            $weekStart->copy()->startOfDay()
                        ) &&
                        $assignedDate->lessThanOrEqualTo(
                            $weekEnd->copy()->endOfDay()
                        )
                    ) {
                        $weekUuids[] = $topic['theme_uuid'];
                    }
                }
            }

            /*
             * Evitar UUIDs duplicados.
             */
            $weekUuids = array_values(
                array_unique($weekUuids)
            );

            /*
             * 8. Registrar el tema como completado.
             */
            StudentDailyProgress::create([
                'id_client' => $studentId,
                'theme_uuid' => $request->theme_uuid,
                'study_date' => $today,
                'due_date' => $dueDate->toDateString(),
                'status' => 'completed',
            ]);

            /*
             * 9. Calcular temas completados de esta semana.
             *
             * Solamente contamos los temas que pertenecen
             * al plan de esta semana.
             */
            $completedCount = StudentDailyProgress::where(
                'id_client',
                $studentId
            )
                ->whereIn('theme_uuid', $weekUuids)
                ->whereBetween('due_date', [
                    $weekStart->toDateString(),
                    $weekEnd->toDateString(),
                ])
                ->where('status', 'completed')
                ->distinct('theme_uuid')
                ->count('theme_uuid');

            /*
             * 10. Calcular porcentaje semanal.
             */
            $totalWeekCount = count($weekUuids);

            $newPercentage = $totalWeekCount > 0
                ? round(($completedCount / $totalWeekCount) * 100)
                : 0;

            /*
             * 11. Actualizar porcentaje semanal
             * en el registro de progreso.
             */
            StudentDailyProgress::where(
                'id_client',
                $studentId
            )
                ->where('theme_uuid', $request->theme_uuid)
                ->where('study_date', $today)
                ->update([
                    'weekly_progress_percentage' => $newPercentage,
                ]);

            /*
             * 12. Actualizar porcentaje del plan.
             */
            $studyPlan->update([
                'progress_percentage' => $newPercentage,
            ]);

            DB::commit();

            return CustomResponse::responseMessage(
                'saveProgressStudent',
                Response::HTTP_CREATED,
                $language
            );

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'Ocurrió un error al guardar el progreso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/student/progress",
     *     summary="Obtener el progreso del plan de estudio semanal",
     *     tags={"Student Progress"},
     *     description="Devuelve el plan semanal completo con el estado de cada tema, el porcentaje de avance y los días completados.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma de la respuesta",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Progreso obtenido exitosamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="daily_distribution", type="integer", example=4),
     *                 @OA\Property(property="weekly_progress_percentage", type="integer", example=45),
     *                 @OA\Property(property="total_week_topics", type="integer", example=24),
     *                 @OA\Property(property="completed_week_topics", type="integer", example=11),
     *                 @OA\Property(property="days_completed", type="object",
     *                     @OA\Property(property="lunes", type="boolean", example=true),
     *                     @OA\Property(property="martes", type="boolean", example=false),
     *                     @OA\Property(property="miércoles", type="boolean", example=false),
     *                     @OA\Property(property="jueves", type="boolean", example=false),
     *                     @OA\Property(property="viernes", type="boolean", example=false),
     *                     @OA\Property(property="sábado", type="boolean", example=false)
     *                 ),
     *                 @OA\Property(property="sunday_review", type="array",
     *
     *                     @OA\Items(type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *                 ),
     *
     *                 @OA\Property(property="weekly_schedule", type="object",
     *                     @OA\Property(property="lunes", type="array",
     *
     *                         @OA\Items(
     *                             type="object",
     *
     *                             @OA\Property(property="theme_uuid", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                             @OA\Property(property="video_url", type="string", format="uri", nullable=true, description="URL temporal autorizada para reproducir el video privado de Backblaze.", example="https://f005.backblazeb2.com/file/drbank/videos/theme.mp4?Authorization=token-temporal"),
     *                             @OA\Property(property="ip_score", type="number", example=0.3333),
     *                             @OA\Property(property="type", type="string", example="review"),
     *                             @OA\Property(property="source", type="string", example="self"),
     *                             @OA\Property(property="assigned_date", type="string", example="2026-08-10"),
     *                             @OA\Property(property="status", type="string", example="completed"),
     *                             @OA\Property(property="is_overdue", type="boolean", example=false)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token no válido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No se encontró un plan activo",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="No tienes un plan de estudio activo")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Ocurrió un error al obtener el progreso")
     *         )
     *     )
     * )
     */
    public function getProgress(BackblazeVideoService $backblazeVideoService)
    {
        $studentId = auth('sanctum')->user()->id_client;

        // 🔥 Configurar zona horaria y localización en español
        Carbon::setLocale('es');
        $nowPeru = Carbon::now('America/Lima');

        // 1. Obtener el plan activo
        $studyPlan = StudentStudyPlan::where('id_client', '=', $studentId, 'and')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->first();

        if (! $studyPlan) {
            return response()->json(['error' => 'No tienes un plan de estudio activo'], 404);
        }

        $planData = json_decode($studyPlan->plan_data, true);

        $allTopics = collect($planData['weekly_schedule'] ?? [])->flatten(1);
        $themeNames = Theme::whereIn('uuid', $allTopics->pluck('theme_uuid')->filter()->unique())
            ->pluck('theme', 'uuid');
        $temporaryVideoUrls = $backblazeVideoService->temporaryUrls(
            $allTopics->pluck('video_url')->filter()->unique()->values()->all()
        );

        // 2. Obtener todos los UUIDs de la semana ACTIVA
        $weekUuids = [];
        $assignedDates = [];
        foreach ($planData['weekly_schedule'] as $day => $topics) {
            foreach ($topics as $topic) {
                $weekUuids[] = $topic['theme_uuid'];

                if (! empty($topic['assigned_date'])) {
                    $assignedDates[] = $topic['assigned_date'];
                }
            }
        }

        $weekUuids = array_values(array_unique($weekUuids));
        $planStartDate = empty($assignedDates) ? null : min($assignedDates);
        $planEndDate = empty($assignedDates) ? null : max($assignedDates);

        // 3. Consultar la tabla de progreso de la semana activa
        $completedUuids = StudentDailyProgress::where('id_client', '=', $studentId, 'and')
            ->whereIn('theme_uuid', $weekUuids)
            ->when($planStartDate && $planEndDate, function ($query) use ($planStartDate, $planEndDate) {
                $query->whereBetween('due_date', [$planStartDate, $planEndDate]);
            })
            ->where('status', 'completed')
            ->distinct()
            ->pluck('theme_uuid')
            ->toArray();

        // 4. CONSTRUIR EL CALENDARIO DE DÍAS (PARA LA APP)
        $calendarDays = [];
        $totalCompletedToday = 0;
        $todayName = strtolower($nowPeru->translatedFormat('l')); // 'lunes', 'martes'... en español

        foreach ($planData['weekly_schedule'] as $day => $topics) {
            $dayTopics = [];
            $completedToday = 0;

            foreach ($topics as $topic) {
                $uuid = $topic['theme_uuid'];
                $isCompleted = in_array($uuid, $completedUuids);

                $assignedDate = Carbon::parse($topic['assigned_date']);

                // 🔥 CORRECCIÓN: Solo es VENCIDO si la fecha ya pasó (es un día posterior)
                // Si es el mismo día, aunque no lo haya hecho, está en PENDING
                $isOverdue = ! $isCompleted && $nowPeru->toDateString() > $assignedDate->toDateString();

                $isToday = ($day === $todayName);

                $dayTopics[] = [
                    'theme_uuid' => $uuid,
                    'theme' => $themeNames->get($uuid, 'Tema sin nombre'),
                    'video_url' => isset($topic['video_url'])
                        ? ($temporaryVideoUrls[$topic['video_url']] ?? $topic['video_url'])
                        : null,
                    'ip_score' => $topic['ip_score'],
                    'type' => $topic['type'],
                    'source' => $topic['source'],
                    'assigned_date' => $topic['assigned_date'],
                    'status' => $isCompleted ? 'completed' : ($isOverdue ? 'overdue' : 'pending'),
                    'is_overdue' => $isOverdue,
                    'is_today' => $isToday,
                ];

                if ($isCompleted) {
                    $completedToday++;
                }
            }

            $dayPercentage = count($topics) > 0 ? round(($completedToday / count($topics)) * 100) : 0;

            // Si es hoy, sumamos para el progreso del día actual
            if ($day === $todayName) {
                $totalCompletedToday = $completedToday;
            }

            // 🔥 Estructura del día para el calendario de la app
            $calendarDays[] = [
                'day_name' => ucfirst($day), // 'Lunes', 'Martes', etc.
                'date' => count($topics) > 0
                    ? Carbon::parse($topics[0]['assigned_date'])->translatedFormat('d \d\e F \d\e Y') // Ej: 16 de agosto de 2026
                    : null,
                'percentage' => $dayPercentage,
                'is_today' => ($day === $todayName),
                'topics' => $dayTopics,
                'is_completed' => $dayPercentage === 100,
                'total_topics' => count($topics),
                'completed_topics' => $completedToday,
            ];
        }

        // 5. Calcular porcentaje de la semana ACTIVA
        $totalWeekCount = count($weekUuids);
        $completedCount = count($completedUuids);
        $percentage = $totalWeekCount > 0 ? round(($completedCount / $totalWeekCount) * 100) : 0;

        // 6. CALCULAR PORCENTAJE HISTÓRICO POR CADA SEMANA
        $allProgress = StudentDailyProgress::where('id_client', '=', $studentId, 'and')
            ->orderBy('due_date', 'asc')
            ->get();

        $weeklyHistory = [];

        if ($allProgress->isNotEmpty()) {
            $weeksMap = [];

            foreach ($allProgress as $progress) {
                $startOfWeek = Carbon::parse($progress->due_date)->startOfWeek(Carbon::MONDAY)->toDateString();

                if (! isset($weeksMap[$startOfWeek])) {
                    $weeksMap[$startOfWeek] = [
                        'week_start' => $startOfWeek,
                        'total_topics' => 0,
                        'completed_topics' => 0,
                    ];
                }

                $weeksMap[$startOfWeek]['total_topics']++;
                $weeksMap[$startOfWeek]['completed_topics']++;
            }

            foreach ($weeksMap as $weekKey => &$weekData) {
                $weekData['percentage'] = $weekData['total_topics'] > 0
                    ? round(($weekData['completed_topics'] / $weekData['total_topics']) * 100)
                    : 0;
            }

            $weeklyHistory = array_values($weeksMap);
        } else {
            // Si no tiene historial, tomamos el plan activo como semana 0%
            $firstWeekStart = null;
            foreach ($planData['weekly_schedule'] as $day => $topics) {
                if (! empty($topics)) {
                    $firstWeekStart = Carbon::parse($topics[0]['assigned_date'])->startOfWeek(Carbon::MONDAY)->toDateString();
                    break;
                }
            }

            if ($firstWeekStart) {
                $weeklyHistory[] = [
                    'week_start' => $firstWeekStart,
                    'total_topics' => $totalWeekCount,
                    'completed_topics' => 0,
                    'percentage' => 0,
                ];
            }
        }

        // 7. Construir la respuesta final
        $responseData = [
            // Resumen global (para la parte superior de la app)
            'summary' => [
                'total_days' => count($calendarDays),
                'weekly_progress_percentage' => $percentage,
                'total_week_topics' => $totalWeekCount,
                'completed_week_topics' => $completedCount,
                'today_progress' => $totalCompletedToday,
                'today_total' => count($planData['weekly_schedule'][$todayName] ?? []),
                'today_name' => ucfirst($todayName), // 'Lunes', 'Martes'...
            ],

            // Lista de días con su detalle
            'calendar' => $calendarDays,
            'sunday_review' => $planData['sunday_review'] ?? [],
            'weekly_history' => $weeklyHistory,
        ];

        return CustomResponse::responseBody($responseData, Response::HTTP_OK);
    }
}
