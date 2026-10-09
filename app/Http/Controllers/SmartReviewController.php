<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Exceptions\InsufficientStratifiedQuestionsException;
use App\Jobs\GenerateSmartReviewPosttestJob;
use App\Services\GoogleQueue;
use App\Services\StratifiedQuestionSelector;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *     schema="SmartReviewExamResult",
 *     type="object",
 *     required={"id_exam", "uuid", "exam_type", "stage", "title", "total_questions", "correct_answers", "incorrect_answers", "unanswered_questions", "score_percentage", "time_spent", "started_at", "completed_at", "status"},
 *
 *     @OA\Property(property="id_exam", type="integer", example=95),
 *     @OA\Property(property="uuid", type="string", format="uuid"),
 *     @OA\Property(property="exam_type", type="string", example="ENAM"),
 *     @OA\Property(property="stage", type="string", enum={"pretest", "review", "posttest"}, example="review"),
 *     @OA\Property(property="title", type="string", example="Repaso adaptativo - Cardiología"),
 *     @OA\Property(property="total_questions", type="integer", example=20),
 *     @OA\Property(property="correct_answers", type="integer", example=16),
 *     @OA\Property(property="incorrect_answers", type="integer", example=3),
 *     @OA\Property(property="unanswered_questions", type="integer", example=1),
 *     @OA\Property(property="score_percentage", type="number", format="float", nullable=true, example=80),
 *     @OA\Property(property="time_spent", type="integer", nullable=true, example=720),
 *     @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="status", type="string", example="completed")
 * ),
 *
 * @OA\Schema(
 *     schema="SmartReviewPaginatedExamResults",
 *     type="object",
 *     required={"status", "data", "total", "page", "limit", "total_pages"},
 *
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SmartReviewExamResult")),
 *     @OA\Property(property="total", type="integer", example=25),
 *     @OA\Property(property="page", type="integer", example=1),
 *     @OA\Property(property="limit", type="integer", example=10),
 *     @OA\Property(property="total_pages", type="integer", example=3)
 * )
 */
class SmartReviewController extends Controller
{
    private const JSON_RESPONSE_OPTIONS = JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE;

    private const SMART_REVIEW_EXAM_TYPE = 'smart review';

    private const MIN_THEMES_PER_BLOCK = 4;

    private const MAX_THEMES_PER_BLOCK = 20;

    private const QUESTIONS_PER_ASSESSMENT = 50;

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/themes",
     *     summary="Obtener temas para el repaso adaptativo",
     *     description="Retorna los temas activos disponibles para un tipo de examen. El campo blocked indica si el tema ya pertenece a un bloque de estudio del usuario autenticado.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma de los mensajes de respuesta",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id_exam_type",
     *         in="query",
     *         description="ID del tipo de examen",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="ENAM")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id_specialty",
     *         in="query",
     *         description="ID de la especialidad por la que se filtrarán los temas",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="id_area",
     *         in="query",
     *         description="ID del área por la que se filtrarán los temas",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Listado de temas obtenido exitosamente",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *                 required={"id", "theme", "id_exam_type", "blocked"},
     *
     *                 @OA\Property(
     *                     property="id",
     *                     type="string",
     *                     format="uuid",
     *                     example="ab49bef0-7d30-11f0-88cc-0200fd8286ac"
     *                 ),
     *                 @OA\Property(property="theme", type="string", example="ANATOMÍA ENAM"),
     *                 @OA\Property(property="id_exam_type", type="string", example="Residentado Médico"),
     *                 @OA\Property(
     *                     property="blocked",
     *                     type="boolean",
     *                     description="Indica si el tema ya está asociado a un bloque de estudio del usuario",
     *                     example=true
     *                 )
     *             ),
     *             example={
     *                 {
     *                     "id": "ab49bef0-7d30-11f0-88cc-0200fd8286ac",
     *                     "theme": "ANATOMÍA ENAM",
     *                     "blocked": true
     *                 },
     *                 {
     *                     "id": "ab49bff1-7d30-11f0-88cc-0200fd8286ac",
     *                     "theme": "FARMACOLOGÍA",
     *                     "blocked": false
     *                 }
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Token no válido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el límite de peticiones",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Se superó el límite de peticiones"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Ocurrió un error, inténtelo nuevamente"
     *             )
     *         )
     *     )
     * )
     */
    public function listThemesAdaptativeReview(Request $request)
    {
        $language = $request->query('lang');

        $validator = Validator::make($request->query(), [
            'id_exam_type' => ['required', 'string', 'max:50'],
            'id_specialty' => ['nullable', 'integer'],
            'id_area' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return CustomResponse::responseBody([
                'status' => false,
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $idClient = auth('sanctum')->user()->id_client;
            $themes = DB::table('questions as q')
                ->join('themes as t', 't.id_theme', '=', 'q.id_theme')
                ->join('specialties as s', 's.id_specialty', '=', 't.id_specialty')
                ->join('areas as a', 'a.id_area', '=', 's.id_area')
                ->where('q.id_exam_type', $request->id_exam_type)
                ->where('q.status', 1)
                ->where('t.status', 1)
                ->where('s.status', 1)
                ->where('a.status', 1)
                ->when(
                    $request->filled('id_specialty'),
                    function ($query) use ($request) {
                        $query->where(
                            't.id_specialty',
                            $request->id_specialty
                        );
                    }
                )
                ->when(
                    $request->filled('id_area'),
                    function ($query) use ($request) {
                        $query->where(
                            's.id_area',
                            $request->id_area
                        );
                    }
                )
                ->select(
                    't.id_theme',
                    't.uuid',
                    't.theme',
                    't.id_specialty',
                    'q.id_exam_type'
                )
                ->selectRaw(
                    '
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM study_block_themes sbt
                        INNER JOIN study_blocks sb
                            ON sb.id_study_block = sbt.id_study_block
                        WHERE sb.id_client = ?
                          AND sbt.id_theme = t.id_theme
                          AND sbt.id_exam_type = q.id_exam_type
                    )
                    THEN 1
                    ELSE 0
                END AS blocked
                ',
                    [$idClient]
                )
                ->distinct()
                ->orderBy('t.theme')
                ->get();
            $response = $themes->map(function ($theme) {
                return [
                    'id' => $theme->uuid,
                    'theme' => $theme->theme,
                    'id_exam_type' => $theme->id_exam_type,
                    'blocked' => (bool) $theme->blocked,
                ];
            });

            return CustomResponse::responseBody($response, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error en listThemesAdaptativeReview: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', 500, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/smart-review/blocks",
     *     summary="Crear un bloque de repaso adaptativo",
     *     description="Crea un nuevo bloque de estudio para el usuario autenticado con un mínimo de 4 y un máximo de 20 temas activos no utilizados previamente.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Temas que formarán parte del bloque de estudio",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"themes"},
     *
     *             @OA\Property(
     *                 property="themes",
     *                 type="array",
     *                 minItems=4,
     *                 maxItems=20,
     *                 uniqueItems=true,
     *                 description="Lista de entre 4 y 20 pares de tema y tipo de examen",
     *
     *                 @OA\Items(type="object", required={"id_theme", "id_exam_type"}, @OA\Property(property="id_theme", type="string", format="uuid"), @OA\Property(property="id_exam_type", type="string", example="ENAM")),
     *                 example={
     *                     {"id_theme":"ab49bef0-7d30-11f0-88cc-0200fd8286ac","id_exam_type":"ENAM"},
     *                     {"id_theme":"ab49bff1-7d30-11f0-88cc-0200fd8286ac","id_exam_type":"ESSALUD"},
     *                     {"id_theme":"ab49c0a2-7d30-11f0-88cc-0200fd8286ac","id_exam_type":"Residentado Médico"},
     *                     {"id_theme":"ab49c153-7d30-11f0-88cc-0200fd8286ac","id_exam_type":"ENAM"}
     *                 }
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Bloque de estudio creado exitosamente",
     *
     *         @OA\JsonContent(type="integer", example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Token no válido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="El bloque anterior todavía no permite crear uno nuevo",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Completa el posttest de tu último bloque antes de incorporar nuevos temas."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos, temas no disponibles o utilizados previamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Uno o más temas no están disponibles."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 nullable=true,
     *                 example={"id": {"The id field must contain 4 items."}}
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el límite de peticiones",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Se superó el límite de peticiones"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ocurrió un error al crear el bloque.")
     *         )
     *     )
     * )
     */
    public function createStoreBlock(Request $request)
    {
        Log::info('request recibido: '.json_encode($request->all()));
        $validator = Validator::make($request->all(), [
            'themes' => [
                'required',
                'array',
                'min:'.self::MIN_THEMES_PER_BLOCK,
                'max:'.self::MAX_THEMES_PER_BLOCK,
            ],
            'themes.*.id_theme' => ['required', 'uuid'],
            'themes.*.id_exam_type' => ['required', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return CustomResponse::responseBody([
                'status' => false,
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $idClient = auth('sanctum')->user()->id_client;

        try {
            $result = DB::transaction(
                function () use ($request, $idClient) {
                    $selections = collect($request->input('themes'));
                    $duplicatePair = $selections->map(
                        fn ($item) => $item['id_theme'].'|'.$item['id_exam_type']
                    )->duplicates()->isNotEmpty();
                    if ($duplicatePair) {
                        throw new \RuntimeException('DUPLICATE_THEME_EXAM_TYPE');
                    }
                    $lastBlock = DB::table('study_blocks')
                        ->where('id_client', $idClient)
                        ->select('posttest_completed_at')
                        ->orderByDesc('id_study_block')
                        ->lockForUpdate()
                        ->first();

                    if ($lastBlock && is_null($lastBlock->posttest_completed_at)) {
                        throw new \RuntimeException('FIRST_POSTTEST_REQUIRED');
                    }

                    $themes = DB::table('themes as t')
                        ->whereIn('t.uuid', $selections->pluck('id_theme'))
                        ->where('t.status', 1)
                        ->select('t.id_theme', 't.uuid')
                        ->get()
                        ->keyBy('uuid');

                    if ($selections->contains(fn ($item) => ! $themes->has($item['id_theme']))) {
                        throw new \RuntimeException('INVALID_THEMES');
                    }

                    foreach ($selections as $selection) {
                        $idTheme = $themes->get($selection['id_theme'])->id_theme;
                        $idExamType = $selection['id_exam_type'];
                        $available = DB::table('questions')->where('id_theme', $idTheme)
                            ->where('id_exam_type', $idExamType)->where('status', 1)->exists();
                        $used = DB::table('study_block_themes as sbt')
                            ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sbt.id_study_block')
                            ->where('sb.id_client', $idClient)->where('sbt.id_theme', $idTheme)
                            ->where('sbt.id_exam_type', $idExamType)->exists();
                        if (! $available) {
                            throw new \RuntimeException('INVALID_THEMES');
                        }
                        if ($used) {
                            throw new \RuntimeException('USED_THEMES');
                        }
                    }

                    $now = now();
                    $idStudyBlock = DB::table('study_blocks')
                        ->insertGetId([
                            'id_client' => $idClient,
                            'status' => 'active',
                            'pretest_completed_at' => null,
                            'posttest_available_at' => null,
                            'posttest_completed_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                    $rows = $selections
                        ->map(fn ($selection) => [
                            'id_study_block' => $idStudyBlock,
                            'id_theme' => $themes->get($selection['id_theme'])->id_theme,
                            'id_exam_type' => $selection['id_exam_type'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all();

                    DB::table('study_block_themes')->insert($rows);

                    return $idStudyBlock;
                }
            );

            return CustomResponse::responseBody($result, Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {

            return match ($e->getMessage()) {

                'FIRST_POSTTEST_REQUIRED' => CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'Completa el posttest de tu último bloque antes de incorporar nuevos temas.',
                ], Response::HTTP_CONFLICT),

                'INVALID_THEMES' => CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'Uno o más temas no están disponibles.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),

                'DUPLICATE_THEME_EXAM_TYPE' => CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'No puedes repetir la misma combinación de tema y tipo de examen.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),

                'USED_THEMES' => CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'Uno o más temas ya forman parte de tu Repaso Adaptativo.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),

                default => CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'No se pudo crear el bloque.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),
            };
        } catch (\Throwable $e) {

            Log::error('storeBlock error', [
                'id_client' => $idClient,
                'error' => $e->getMessage(),
            ]);

            return CustomResponse::responseBody([
                'status' => false,
                'message' => 'Ocurrió un error al crear el bloque.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/smart-review/blocks/{id}/pretest",
     *     summary="Generar la evaluación inicial de un bloque",
     *     description="Genera 50 preguntas aleatorias distribuidas equitativamente entre los temas del bloque de estudio del usuario autenticado.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del bloque de estudio",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma de los mensajes de respuesta",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Evaluación inicial generada exitosamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"id_study_block", "questions"},
     *
     *             @OA\Property(property="id_study_block", type="integer", example=15),
     *             @OA\Property(
     *                 property="questions",
     *                 type="array",
     *                 minItems=50,
     *                 maxItems=50,
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id", "id_theme", "theme", "id_exam_type", "question", "image", "alternatives"},
     *
     *                     @OA\Property(property="id", type="integer", example=589),
     *                     @OA\Property(property="id_theme", type="string", format="uuid"),
     *                     @OA\Property(property="theme", type="string", example="CARDIOLOGÍA"),
     *                     @OA\Property(property="id_exam_type", type="string", example="ENAM"),
     *                     @OA\Property(
     *                         property="question",
     *                         type="string",
     *                         example="Paciente con dolor torácico súbito, ¿cuál es el diagnóstico más probable?"
     *                     ),
     *                     @OA\Property(
     *                         property="image",
     *                         type="string",
     *                         nullable=true,
     *                         example=null
     *                     ),
     *                     @OA\Property(
     *                         property="alternatives",
     *                         type="object",
     *                         required={"a", "b", "c", "d", "e"},
     *                         @OA\Property(property="a", type="string", nullable=true, example="Angina estable"),
     *                         @OA\Property(property="b", type="string", nullable=true, example="Infarto agudo de miocardio"),
     *                         @OA\Property(property="c", type="string", nullable=true, example="Pericarditis"),
     *                         @OA\Property(property="d", type="string", nullable=true, example="Neumonía"),
     *                         @OA\Property(property="e", type="string", nullable=true, example="Reflujo gastroesofágico")
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
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Token no válido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Bloque no encontrado",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bloque no encontrado.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="La evaluación inicial ya fue completada",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="La evaluación inicial de este bloque ya fue completada."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="El bloque no cumple las condiciones necesarias para generar el pretest",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="El bloque debe contener entre 4 y 20 temas."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="theme", type="string", example="CARDIOLOGÍA"),
     *                 @OA\Property(property="required", type="integer", example=13),
     *                 @OA\Property(property="available", type="integer", example=8)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No se pudo generar la evaluación inicial."
     *             )
     *         )
     *     )
     * )
     */
    public function generatePretest(int $id)
    {
        $idClient = auth('sanctum')->user()->id_client;
        $themes = collect();

        try {
            $block = DB::table('study_blocks')
                ->where('id_study_block', $id)
                ->where('id_client', $idClient)
                ->where('status', 'active')
                ->select('id_study_block', 'pretest_completed_at')
                ->first();

            if (! $block) {
                return CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'Bloque no encontrado.',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($block->pretest_completed_at !== null) {
                return CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'La evaluación inicial de este bloque ya fue completada.',
                ], Response::HTTP_CONFLICT);
            }

            $themes = DB::table('study_block_themes as sbt')
                ->join('themes as t', 't.id_theme', '=', 'sbt.id_theme')
                ->where('sbt.id_study_block', $block->id_study_block)
                ->where('t.status', 1)
                ->select('t.id_theme', 't.theme', 'sbt.id_exam_type')
                ->orderBy('sbt.id_study_block_theme')
                ->get();

            if (
                $themes->count() < self::MIN_THEMES_PER_BLOCK
                || $themes->count() > self::MAX_THEMES_PER_BLOCK
            ) {
                return CustomResponse::responseBody([
                    'status' => false,
                    'message' => 'El bloque debe contener entre 4 y 20 temas.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $baseQuestions = intdiv(self::QUESTIONS_PER_ASSESSMENT, $themes->count());
            $remainder = self::QUESTIONS_PER_ASSESSMENT % $themes->count();
            $requirements = $themes->values()->map(
                fn ($theme, $index) => [
                    'id_theme' => (int) $theme->id_theme,
                    'id_exam_type' => (string) $theme->id_exam_type,
                    'required' => $baseQuestions + ($index < $remainder ? 1 : 0),
                ]
            )->all();
            $selectedByTheme = app(StratifiedQuestionSelector::class)
                ->selectPairs($requirements);
            $responseQuestions = $selectedByTheme
                ->flatten(1)
                ->map(fn ($question) => [
                    'id' => $question->id_question,
                    'id_theme' => $question->theme_uuid,
                    'theme' => $question->theme,
                    'id_exam_type' => $question->id_exam_type,
                    'question' => $question->question,
                    'image' => $question->image,
                    'alternatives' => [
                        'a' => $question->alt_a,
                        'b' => $question->alt_b,
                        'c' => $question->alt_c,
                        'd' => $question->alt_d,
                        'e' => $question->alt_e,
                    ],
                    'response' => $question->response,
                    'distractor_analysis' => $question->distractor_analysis,
                    'justification' => $question->justification,
                    'reference' => $question->reference,
                ])
                ->shuffle()
                ->values();

            return response()->json([
                'id_study_block' => $block->id_study_block,
                'questions' => $responseQuestions,
            ], Response::HTTP_OK, [], self::JSON_RESPONSE_OPTIONS);
        } catch (InsufficientStratifiedQuestionsException $e) {
            $theme = $themes->firstWhere('id_theme', $e->idTheme);
            $themeName = $theme?->theme ?? "ID {$e->idTheme}";

            return CustomResponse::responseBody([
                'status' => false,
                'message' => "El tema {$themeName} no tiene suficientes preguntas para generar el pretest.",
                'data' => [
                    'theme' => $themeName,
                    'required' => $e->required,
                    'available' => $e->available,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $th) {
            Log::error(
                'Error generatePretest',
                [
                    'id_client' => $idClient,

                    'id_study_block' => $id,

                    'error' => $th->getMessage(),
                ]
            );

            return CustomResponse::responseBody([
                'status' => false,

                'message' => 'No se pudo generar la evaluación inicial.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/pretest/complete",
     *     summary="Completar la evaluación inicial de un bloque",
     *     description="Guarda las 50 respuestas y programa en segundo plano el cálculo del resultado y la inicialización del repaso adaptativo.",
     *     operationId="completeSmartReviewPretest",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="idStudyBlock",
     *         in="path",
     *         required=true,
     *         description="ID del bloque de estudio",
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"title", "score_percentage", "time_spent", "started_at", "completed_at", "answers"},
     *
     *             @OA\Property(property="title", type="string", maxLength=255, example="Mi evaluación inicial"),
     *             @OA\Property(
     *                 property="score_percentage",
     *                 type="number",
     *                 format="float",
     *                 minimum=0,
     *                 maximum=100,
     *                 example=76
     *             ),
     *             @OA\Property(
     *                 property="time_spent",
     *                 type="integer",
     *                 minimum=0,
     *                 description="Tiempo empleado en segundos",
     *                 example=1320
     *             ),
     *             @OA\Property(
     *                 property="started_at",
     *                 type="string",
     *                 format="date-time",
     *                 example="2026-09-26T13:00:00-05:00"
     *             ),
     *             @OA\Property(
     *                 property="completed_at",
     *                 type="string",
     *                 format="date-time",
     *                 example="2026-09-26T13:22:00-05:00"
     *             ),
     *             @OA\Property(
     *                 property="answers",
     *                 type="array",
     *                 minItems=50,
     *                 maxItems=50,
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id_question", "answer", "difficulty"},
     *
     *                     @OA\Property(property="id_question", type="integer", example=589),
     *                     @OA\Property(property="answer", type="string", enum={"A", "B", "C", "D", "E"}, example="B"),
     *                     @OA\Property(property="difficulty", type="string", enum={"hard", "regular", "easy"}, example="regular")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Evaluación inicial recibida para procesamiento",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message", "data"},
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Evaluación inicial recibida y enviada a procesamiento."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id_exam", type="integer", example=81),
     *                 @OA\Property(property="uuid", type="string", format="uuid", example="6b9796d7-ff9a-47f4-91ec-c2ebde454931"),
     *                 @OA\Property(property="id_study_block", type="integer", example=15),
     *                 @OA\Property(property="exam_type", type="string", example="smart review"),
     *                 @OA\Property(property="score_percentage", type="number", format="float", example=76),
     *                 @OA\Property(property="processing_status", type="string", enum={"pending"}, example="pending"),
     *                 @OA\Property(property="pretest_completed_at", type="string", format="date-time", example="2026-09-25 14:30:00"),
     *                 @OA\Property(property="posttest_available_at", type="string", format="date-time", example="2026-10-23 14:30:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(
     *         response=404,
     *         description="Bloque no encontrado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="El bloque de Repaso Adaptativo no existe.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Bloque inactivo o pretest ya completado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="La evaluación inicial de este bloque ya fue completada.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Formato de datos inválido",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Datos inválidos."),
     *             @OA\Property(property="errors", type="object", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Ocurrió un error al completar la evaluación inicial.")
     *         )
     *     )
     * )
     */
    public function completePretest(
        Request $request,
        int $idStudyBlock
    ) {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'score_percentage' => ['required', 'numeric'],
            'time_spent' => ['required', 'integer', 'min:0'],
            'started_at' => ['required', 'date'],
            'completed_at' => ['required', 'date', 'after_or_equal:started_at'],
            'answers' => ['required', 'array', 'size:50'],
            'answers.*.id_question' => ['required', 'integer', 'distinct'],
            'answers.*.answer' => ['required', 'string'],
            'answers.*.difficulty' => ['required', 'string', 'in:hard,regular,easy'],
        ], [
            'answers.size' => 'El pretest debe contener exactamente 50 respuestas.',
            'answers.*.id_question.distinct' => 'No se puede enviar una misma pregunta más de una vez.',
            'answers.*.answer.in' => 'La alternativa seleccionada no es válida.',
            'answers.*.difficulty.in' => 'La dificultad debe ser hard, regular o easy.',
        ]);

        if ($validator->fails()) {
            Log::warning('Validación completePretest fallida', [
                'id_study_block' => $idStudyBlock,
                'received_fields' => array_keys($request->all()),
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $idClient = (int) auth('sanctum')->user()->id_client;
        $validated = $validator->validated();
        $examSummary = collect($validated['answers'])
            ->map(fn (array $answer) => [
                'question_id' => (int) $answer['id_question'],
                'response' => strtoupper(trim($answer['answer'])),
                'difficulty' => strtolower(trim($answer['difficulty'])),
            ])
            ->values()
            ->all();

        try {
            $result = DB::transaction(function () use (
                $idClient,
                $idStudyBlock,
                $validated,
                $examSummary
            ) {
                $block = DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->select('status', 'pretest_completed_at')
                    ->lockForUpdate()
                    ->first();

                if (! $block) {
                    throw new \RuntimeException('BLOCK_NOT_FOUND');
                }

                if ($block->status !== 'active') {
                    throw new \RuntimeException('BLOCK_NOT_ACTIVE');
                }

                if ($block->pretest_completed_at !== null) {
                    throw new \RuntimeException('PRETEST_ALREADY_COMPLETED');
                }

                $now = now();
                $startedAt = Carbon::parse($validated['started_at'])
                    ->setTimezone('America/Lima');
                $completedAt = Carbon::parse($validated['completed_at'])
                    ->setTimezone('America/Lima');
                $examUuid = (string) Str::uuid();
                $idExam = DB::table('exams')->insertGetId([
                    'id_client' => $idClient,
                    'id_study_block' => $idStudyBlock,
                    'uuid' => $examUuid,
                    'exam_type' => self::SMART_REVIEW_EXAM_TYPE,
                    'smart_review_stage' => 'pretest',
                    'title' => $validated['title'],
                    'total_questions' => count($examSummary),
                    'score_percentage' => (float) $validated['score_percentage'],
                    'time_spent' => (int) $validated['time_spent'],
                    'exam_summary' => json_encode($examSummary, JSON_UNESCAPED_UNICODE),
                    'status' => 'completed',
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $posttestAvailableAt = $completedAt->copy()->addDays(28);

                DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->update([
                        'pretest_completed_at' => $completedAt,
                        'posttest_available_at' => $posttestAvailableAt,
                        'updated_at' => $now,
                    ]);

                return [
                    'id_exam' => $idExam,
                    'uuid' => $examUuid,
                    'title' => $validated['title'],
                    'id_study_block' => $idStudyBlock,
                    'exam_type' => self::SMART_REVIEW_EXAM_TYPE,
                    'score_percentage' => (float) $validated['score_percentage'],
                    'processing_status' => 'pending',
                    'pretest_completed_at' => $completedAt->toDateTimeString(),
                    'posttest_available_at' => $posttestAvailableAt->toDateTimeString(),
                ];
            });

            try {
                GoogleQueue::sendQueue([
                    'value' => [
                        'type' => 5,
                        'id_client' => $idClient,
                        'id_study_block' => (int) $result['id_study_block'],
                        'id_exam' => (int) $result['id_exam'],
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::error('No se pudo publicar el pretest en Google Pub/Sub', [
                    'id_client' => $idClient,
                    'id_study_block' => $idStudyBlock,
                    'id_exam' => $result['id_exam'],
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Evaluación inicial recibida y enviada a procesamiento.',
                'data' => $result,
            ], Response::HTTP_ACCEPTED);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'BLOCK_NOT_FOUND' => response()->json([
                    'status' => false,
                    'message' => 'El bloque de Repaso Adaptativo no existe.',
                ], Response::HTTP_NOT_FOUND),
                'BLOCK_NOT_ACTIVE' => response()->json([
                    'status' => false,
                    'message' => 'El bloque de Repaso Adaptativo no está activo.',
                ], Response::HTTP_CONFLICT),
                'PRETEST_ALREADY_COMPLETED' => response()->json([
                    'status' => false,
                    'message' => 'La evaluación inicial de este bloque ya fue completada.',
                ], Response::HTTP_CONFLICT),
                default => response()->json([
                    'status' => false,
                    'message' => 'No se pudo completar la evaluación inicial.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),
            };
        } catch (\Throwable $e) {
            Log::error('Error completePretest', [
                'id_client' => $idClient,
                'id_study_block' => $idStudyBlock,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error al completar la evaluación inicial.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/blocks",
     *     summary="Consultar el repaso adaptativo del usuario",
     *     description="Retorna bloques, temas, progreso y cantidades de exámenes. Los pretests, repasos y posttests se consultan mediante sus endpoints paginados por bloque.",
     *     operationId="showSmartReview",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Estado del repaso adaptativo obtenido correctamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "data"},
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"has_smart_review", "can_select_new_themes", "theme_selection_status", "minimum_themes_per_selection", "maximum_themes_per_selection", "selection_blocked_by_study_block", "next_selection_posttest_at", "theme_selection_message", "blocks"},
     *                 @OA\Property(
     *                     property="has_smart_review",
     *                     type="boolean",
     *                     description="Indica si el usuario tiene al menos un bloque",
     *                     example=true
     *                 ),
     *                 @OA\Property(
     *                     property="can_select_new_themes",
     *                     type="boolean",
     *                     description="Indica si el estudiante puede seleccionar entre 4 y 20 temas nuevos",
     *                     example=false
     *                 ),
     *                 @OA\Property(
     *                     property="theme_selection_status",
     *                     type="string",
     *                     enum={"available", "waiting_posttest"},
     *                     example="waiting_posttest"
     *                 ),
     *                 @OA\Property(property="minimum_themes_per_selection", type="integer", example=4),
     *                 @OA\Property(property="maximum_themes_per_selection", type="integer", example=20),
     *                 @OA\Property(property="selection_blocked_by_study_block", type="integer", nullable=true, example=15),
     *                 @OA\Property(property="next_selection_posttest_at", type="string", format="date-time", nullable=true, example="2026-10-25 19:07:11"),
     *                 @OA\Property(
     *                     property="theme_selection_message",
     *                     type="string",
     *                     example="Completa el posttest de tu último bloque para seleccionar nuevos temas."
     *                 ),
     *                 @OA\Property(
     *                     property="blocks",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *                         required={"id_study_block", "status", "exam_type", "exam_types", "pretest_score_percentage", "pretest_completed_at", "posttest_score_percentage", "posttest_completed_at", "posttest_available", "due_reviews", "total_reviews", "completed_reviews", "review_progress_percentage", "exam_counts", "themes"},
     *
     *                         @OA\Property(property="id_study_block", type="integer", example=15),
     *                         @OA\Property(property="status", type="string", example="active"),
     *                         @OA\Property(property="exam_type", type="string", nullable=true, example="ENAM", description="Solo se llena cuando todos los temas pertenecen al mismo tipo de examen"),
     *                         @OA\Property(property="exam_types", type="array", @OA\Items(type="string"), example={"ENAM", "Residentado Médico"}),
     *                         @OA\Property(property="pretest_score_percentage", type="number", format="float", nullable=true, example=72.5),
     *                         @OA\Property(property="pretest_completed_at", type="string", format="date-time", nullable=true, example="2026-09-25 14:30:00"),
     *                         @OA\Property(property="posttest_score_percentage", type="number", format="float", nullable=true, example=88),
     *                         @OA\Property(property="posttest_completed_at", type="string", format="date-time", nullable=true, example="2026-10-23 15:10:00"),
     *                         @OA\Property(property="posttest_available_at", type="string", format="date-time", nullable=true, example="2026-10-23 14:30:00"),
     *                         @OA\Property(property="posttest_available", type="boolean", example=false),
     *                         @OA\Property(property="due_reviews", type="integer", minimum=0, example=7),
     *                         @OA\Property(property="total_reviews", type="integer", minimum=0, example=20),
     *                         @OA\Property(property="completed_reviews", type="integer", minimum=0, example=8),
     *                         @OA\Property(property="review_progress_percentage", type="number", format="float", minimum=0, maximum=100, example=40),
     *                         @OA\Property(
     *                             property="exam_counts",
     *                             type="object",
     *                             required={"pretests", "reviews", "posttests"},
     *                             @OA\Property(property="pretests", type="integer", minimum=0, example=1),
     *                             @OA\Property(property="reviews", type="integer", minimum=0, example=8),
     *                             @OA\Property(property="posttests", type="integer", minimum=0, example=2)
     *                         ),
     *                         @OA\Property(
     *                             property="themes",
     *                             type="array",
     *
     *                             @OA\Items(
     *                                 type="object",
     *                                 required={"uuid", "theme", "specialty", "area", "id_exam_type"},
     *
     *                                 @OA\Property(property="uuid", type="string", format="uuid", example="ab49bef0-7d30-11f0-88cc-0200fd8286ac"),
     *                                 @OA\Property(property="theme", type="string", example="CARDIOLOGÍA"),
     *                                 @OA\Property(property="specialty", type="string", example="CARDIOLOGÍA"),
     *                                 @OA\Property(property="area", type="string", example="MEDICINA"),
     *                                 @OA\Property(property="id_exam_type", type="string", example="ENAM")
     *                             )
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
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Token no válido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo consultar el repaso adaptativo.")
     *         )
     *     )
     * )
     */
    public function showSmartReview()
    {
        return $this->smartReviewBlocksResponse();
    }

    private function smartReviewBlocksResponse()
    {
        $idClient = (int) auth('sanctum')->user()->id_client;
        $now = now();

        $blocks = DB::table('study_blocks as sb')
            ->where('sb.id_client', $idClient)
            ->select(
                'sb.id_study_block',
                'sb.status',
                'sb.pretest_completed_at',
                'sb.posttest_available_at',
                'sb.posttest_completed_at'
            )
            ->orderByDesc('sb.id_study_block')
            ->get();

        $lastBlock = $blocks->first();
        $canSelectNewThemes = $lastBlock === null
            || ! empty($lastBlock->posttest_completed_at);
        $themeSelectionState = [
            'can_select_new_themes' => $canSelectNewThemes,
            'theme_selection_status' => $canSelectNewThemes
                ? 'available'
                : 'waiting_posttest',
            'minimum_themes_per_selection' => self::MIN_THEMES_PER_BLOCK,
            'maximum_themes_per_selection' => self::MAX_THEMES_PER_BLOCK,
            'selection_blocked_by_study_block' => $canSelectNewThemes
                ? null
                : (int) $lastBlock->id_study_block,
            'next_selection_posttest_at' => $canSelectNewThemes
                ? null
                : $lastBlock->posttest_available_at,
            'theme_selection_message' => $canSelectNewThemes
                ? 'Puedes seleccionar entre 4 y 20 temas nuevos.'
                : 'Completa el posttest de tu último bloque para seleccionar nuevos temas.',
        ];

        if ($blocks->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => array_merge([
                    'has_smart_review' => false,
                    'blocks' => [],
                ], $themeSelectionState),
            ]);
        }

        $blockIds = $blocks->pluck('id_study_block');

        $themesByBlock = DB::table('study_block_themes as sbt')
            ->join('themes as t', 't.id_theme', '=', 'sbt.id_theme')
            ->join('specialties as s', 's.id_specialty', '=', 't.id_specialty')
            ->join('areas as a', 'a.id_area', '=', 's.id_area')
            ->whereIn('sbt.id_study_block', $blockIds)
            ->select(
                'sbt.id_study_block',
                't.id_theme',
                't.uuid',
                't.theme',
                's.specialty',
                'a.area',
                'sbt.id_exam_type'
            )
            ->orderBy('sbt.id_study_block_theme')
            ->get()
            ->groupBy('id_study_block');

        $examMetadataByBlock = DB::table('exams')
            ->where('id_client', $idClient)
            ->whereIn('id_study_block', $blockIds)
            ->whereIn('smart_review_stage', ['pretest', 'review', 'posttest'])
            ->where('status', 'completed')
            ->select(
                'id_exam',
                'id_study_block',
                'smart_review_stage',
                'score_percentage'
            )
            ->orderByDesc('id_exam')
            ->get()
            ->groupBy('id_study_block');

        $pretestSummariesByBlock = DB::table('exams')
            ->where('id_client', $idClient)
            ->whereIn('id_study_block', $blockIds)
            ->where('smart_review_stage', 'pretest')
            ->where('status', 'completed')
            ->select('id_exam', 'id_study_block', 'exam_summary')
            ->orderByDesc('id_exam')
            ->get()
            ->groupBy('id_study_block')
            ->map->first();
        $questionIdsByBlock = $pretestSummariesByBlock->map(function ($exam) {
            return collect(json_decode($exam->exam_summary ?? '[]', true) ?: [])
                ->pluck('question_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        });
        $examTypeByQuestion = DB::table('questions')
            ->whereIn(
                'id_question',
                $questionIdsByBlock->flatten()->unique()->values()
            )
            ->pluck('id_exam_type', 'id_question');
        $examTypeByBlock = $questionIdsByBlock->map(
            fn ($questionIds) => $questionIds
                ->map(fn ($idQuestion) => $examTypeByQuestion->get($idQuestion))
                ->filter()
                ->first()
        );

        $dueReviewsByBlock = DB::table('student_theme_reviews as str')
            ->join('study_block_themes as sbt', function ($join) {
                $join->on('sbt.id_theme', '=', 'str.id_theme')
                    ->on('sbt.id_exam_type', '=', 'str.id_exam_type');
            })
            ->where('str.id_client', $idClient)
            ->whereIn('sbt.id_study_block', $blockIds)
            ->where('str.next_review_at', '<=', $now)
            ->groupBy('sbt.id_study_block')
            ->selectRaw('sbt.id_study_block, COUNT(*) as due_reviews')
            ->pluck('due_reviews', 'id_study_block');

        $reviewProgressByBlock = DB::table('study_block_themes as sbt')
            ->leftJoin('student_theme_reviews as str', function ($join) use ($idClient) {
                $join->on(
                    'str.id_theme',
                    '=',
                    'sbt.id_theme'
                )->on(
                    'str.id_exam_type',
                    '=',
                    'sbt.id_exam_type'
                )->where('str.id_client', $idClient);
            })
            ->whereIn('sbt.id_study_block', $blockIds)
            ->groupBy('sbt.id_study_block')
            ->selectRaw(
                'sbt.id_study_block,
                COUNT(*) as total_reviews,
                COUNT(DISTINCT CASE
                    WHEN str.last_reviewed_at > str.initialized_at
                    THEN str.id_student_theme_review
                END) as completed_reviews'
            )
            ->get()
            ->keyBy('id_study_block');

        $data = $blocks->map(
            function ($block) use (
                $now,
                $themesByBlock,
                $examMetadataByBlock,
                $dueReviewsByBlock,
                $reviewProgressByBlock
            ) {
                $blockExams = $examMetadataByBlock->get(
                    $block->id_study_block,
                    collect()
                );
                $pretest = $blockExams->firstWhere('smart_review_stage', 'pretest');
                $posttest = $blockExams->firstWhere('smart_review_stage', 'posttest');
                $progress = $reviewProgressByBlock->get($block->id_study_block);
                $totalReviews = (int) ($progress->total_reviews ?? 0);
                $completedReviews = (int) ($progress->completed_reviews ?? 0);
                $blockExamTypes = $themesByBlock
                    ->get($block->id_study_block, collect())
                    ->pluck('id_exam_type')
                    ->unique()
                    ->values();

                return [
                    'id_study_block' => $block->id_study_block,
                    'status' => $block->status,
                    'exam_type' => $blockExamTypes->count() === 1
                        ? $blockExamTypes->first()
                        : null,
                    'exam_types' => $blockExamTypes,
                    'pretest_score_percentage' => $pretest !== null
                        ? (float) $pretest->score_percentage
                        : null,
                    'pretest_completed_at' => $block->pretest_completed_at,
                    'posttest_score_percentage' => $posttest !== null
                        ? (float) $posttest->score_percentage
                        : null,
                    'posttest_completed_at' => $block->posttest_completed_at,
                    'posttest_available_at' => $block->posttest_available_at,
                    'posttest_available' => ! empty($block->posttest_available_at)
                        && $now->gte($block->posttest_available_at),
                    'due_reviews' => (int) $dueReviewsByBlock->get($block->id_study_block, 0),
                    'total_reviews' => $totalReviews,
                    'completed_reviews' => $completedReviews,
                    'review_progress_percentage' => $totalReviews > 0
                        ? round(($completedReviews / $totalReviews) * 100, 2)
                        : 0.0,
                    'exam_counts' => [
                        'pretests' => $blockExams->where('smart_review_stage', 'pretest')->count(),
                        'reviews' => $blockExams->where('smart_review_stage', 'review')->count(),
                        'posttests' => $blockExams->where('smart_review_stage', 'posttest')->count(),
                    ],
                    'themes' => $themesByBlock
                        ->get($block->id_study_block, collect())
                        ->map(fn ($theme) => [
                            'uuid' => $theme->uuid,
                            'theme' => $theme->theme,
                            'specialty' => $theme->specialty,
                            'area' => $theme->area,
                            'id_exam_type' => $theme->id_exam_type,
                        ])
                        ->values(),
                ];
            }
        )->values();

        return response()->json([
            'status' => true,
            'data' => array_merge([
                'has_smart_review' => true,
                'blocks' => $data,
            ], $themeSelectionState),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/pretests",
     *     operationId="getSmartReviewBlockPretest",
     *     summary="Obtener el pretest de un bloque",
     *     description="Retorna el único pretest completado del bloque. Este endpoint no utiliza paginación.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="idStudyBlock", in="path", required=true, description="ID del bloque de estudio", @OA\Schema(type="integer", minimum=1, example=8)),
     *
     *     @OA\Response(response=200, description="Pretest del bloque (data es null si aún no existe)", @OA\JsonContent(type="object", required={"status", "data"}, @OA\Property(property="status", type="boolean", example=true), @OA\Property(property="data", nullable=true, oneOf={@OA\Schema(ref="#/components/schemas/SmartReviewExamResult")}))),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="Bloque no encontrado")
     * )
     */
    public function listBlockPretests(Request $request, int $idStudyBlock)
    {
        return $this->listBlockExams($request, $idStudyBlock, 'pretest');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/reviews",
     *     operationId="listSmartReviewBlockReviews",
     *     summary="Listar repasos completados de un bloque",
     *     description="Retorna metadatos paginados de los repasos; las preguntas se consultan mediante el UUID del examen.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="idStudyBlock", in="path", required=true, description="ID del bloque de estudio", @OA\Schema(type="integer", minimum=1, example=8)),
     *     @OA\Parameter(name="page", in="query", required=true, description="Número de página", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="limit", in="query", required=true, description="Cantidad de resultados por página", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *
     *     @OA\Response(response=200, description="Repasos paginados", @OA\JsonContent(ref="#/components/schemas/SmartReviewPaginatedExamResults")),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="Bloque no encontrado"),
     *     @OA\Response(response=422, description="Faltan page o limit, o sus valores no son válidos")
     * )
     */
    public function listBlockReviews(Request $request, int $idStudyBlock)
    {
        return $this->listBlockExams($request, $idStudyBlock, 'review');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/posttests",
     *     operationId="listSmartReviewBlockPosttests",
     *     summary="Listar posttests completados de un bloque",
     *     description="Retorna metadatos paginados de los posttests; las preguntas se consultan mediante el UUID del examen.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="idStudyBlock", in="path", required=true, description="ID del bloque de estudio", @OA\Schema(type="integer", minimum=1, example=8)),
     *     @OA\Parameter(name="page", in="query", required=true, description="Número de página", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="limit", in="query", required=true, description="Cantidad de resultados por página", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *
     *     @OA\Response(response=200, description="Posttests paginados", @OA\JsonContent(ref="#/components/schemas/SmartReviewPaginatedExamResults")),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="Bloque no encontrado"),
     *     @OA\Response(response=422, description="Faltan page o limit, o sus valores no son válidos")
     * )
     */
    public function listBlockPosttests(Request $request, int $idStudyBlock)
    {
        return $this->listBlockExams($request, $idStudyBlock, 'posttest');
    }

    private function listBlockExams(
        Request $request,
        int $idStudyBlock,
        string $stage
    ) {
        $isPretest = $stage === 'pretest';
        $validator = Validator::make($request->query(), $isPretest ? [] : [
            'page' => ['required', 'integer', 'min:1'],
            'limit' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Los parámetros de paginación no son válidos.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $idClient = (int) auth('sanctum')->user()->id_client;
        $blockExists = DB::table('study_blocks')
            ->where('id_study_block', $idStudyBlock)
            ->where('id_client', $idClient)
            ->exists();

        if (! $blockExists) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontró el bloque de repaso adaptativo.',
            ], Response::HTTP_NOT_FOUND);
        }

        $query = DB::table('exams')
            ->where('id_client', $idClient)
            ->where('id_study_block', $idStudyBlock)
            ->where('smart_review_stage', $stage)
            ->where('status', 'completed')
            ->select(
                'id_exam',
                'uuid',
                'smart_review_stage',
                'title',
                'total_questions',
                'score_percentage',
                'time_spent',
                'exam_summary',
                'started_at',
                'completed_at',
                'status'
            )
            ->orderByDesc('id_exam');

        $exams = $isPretest
            ? collect([$query->first()])->filter()
            : $query->paginate(
                (int) $request->query('limit'),
                ['*'],
                'page',
                (int) $request->query('page')
            );

        $examItems = $isPretest ? $exams : collect($exams->items());

        $questionIdsByExam = $examItems->mapWithKeys(function ($exam) {
            $questionIds = collect(json_decode($exam->exam_summary ?? '[]', true) ?: [])
                ->pluck('question_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            return [(int) $exam->id_exam => $questionIds];
        });
        $examTypeByQuestion = DB::table('questions')
            ->whereIn('id_question', $questionIdsByExam->flatten()->unique()->values())
            ->pluck('id_exam_type', 'id_question');
        $items = $examItems->map(function ($exam) use (
            $questionIdsByExam,
            $examTypeByQuestion
        ) {
            $summary = collect(json_decode($exam->exam_summary ?? '[]', true) ?: []);
            $correctAnswers = $summary
                ->filter(fn ($answer) => ($answer['correct'] ?? false) === true)
                ->count();
            $answeredQuestions = $summary
                ->filter(fn ($answer) => trim((string) ($answer['response'] ?? '')) !== '')
                ->count();
            $totalQuestions = (int) $exam->total_questions;
            $examType = $questionIdsByExam
                ->get((int) $exam->id_exam, collect())
                ->map(fn ($idQuestion) => $examTypeByQuestion->get($idQuestion))
                ->filter()
                ->first();

            return [
                'id_exam' => (int) $exam->id_exam,
                'uuid' => $exam->uuid,
                'exam_type' => $examType,
                'stage' => $exam->smart_review_stage,
                'title' => $exam->title,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'incorrect_answers' => max(0, $answeredQuestions - $correctAnswers),
                'unanswered_questions' => max(0, $totalQuestions - $answeredQuestions),
                'score_percentage' => $exam->score_percentage !== null
                    ? (float) $exam->score_percentage
                    : null,
                'time_spent' => $exam->time_spent !== null
                    ? (int) $exam->time_spent
                    : null,
                'started_at' => $exam->started_at,
                'completed_at' => $exam->completed_at,
                'status' => $exam->status,
            ];
        })->values();

        if ($isPretest) {
            return response()->json([
                'status' => true,
                'data' => $items->first(),
            ], Response::HTTP_OK, [], self::JSON_RESPONSE_OPTIONS);
        }

        return response()->json([
            'status' => true,
            'data' => $items,
            'total' => $exams->total(),
            'page' => $exams->currentPage(),
            'limit' => $exams->perPage(),
            'total_pages' => $exams->lastPage(),
        ], Response::HTTP_OK, [], self::JSON_RESPONSE_OPTIONS);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}",
     *     summary="Eliminar un bloque de repaso adaptativo",
     *     description="Elimina de forma atómica un bloque perteneciente al estudiante autenticado, sus exámenes, asignaciones, temas asociados y estados SM-2 exclusivos del bloque. Los estados compartidos con otro bloque se conservan.",
     *     operationId="deleteSmartReviewStudyBlock",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="idStudyBlock",
     *         in="path",
     *         required=true,
     *         description="ID del bloque que se desea eliminar",
     *
     *         @OA\Schema(type="integer", minimum=1, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Bloque eliminado correctamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message", "data"},
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bloque de repaso adaptativo eliminado correctamente."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"id_study_block", "deleted_exams", "deleted_assignments", "deleted_theme_reviews", "deleted_themes"},
     *                 @OA\Property(property="id_study_block", type="integer", example=15),
     *                 @OA\Property(property="deleted_exams", type="integer", example=3),
     *                 @OA\Property(property="deleted_assignments", type="integer", example=20),
     *                 @OA\Property(property="deleted_theme_reviews", type="integer", example=4),
     *                 @OA\Property(property="deleted_themes", type="integer", example=4)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Token no válido"))
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Bloque inexistente o perteneciente a otro estudiante",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se encontró el bloque de repaso adaptativo.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo eliminar el bloque de repaso adaptativo.")
     *         )
     *     )
     * )
     */
    public function deleteStudyBlock(int $idStudyBlock)
    {
        $idClient = (int) auth('sanctum')->user()->id_client;

        try {
            $deleted = DB::transaction(function () use ($idClient, $idStudyBlock) {
                $block = DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->lockForUpdate()
                    ->first();

                if (! $block) {
                    throw new \RuntimeException('BLOCK_NOT_FOUND');
                }

                $blockThemes = DB::table('study_block_themes')
                    ->where('id_study_block', $idStudyBlock)
                    ->select('id_theme', 'id_exam_type')
                    ->get();

                $sharedThemePairs = DB::table('study_block_themes as sbt')
                    ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sbt.id_study_block')
                    ->where('sb.id_client', $idClient)
                    ->where('sb.id_study_block', '<>', $idStudyBlock)
                    ->whereIn('sbt.id_theme', $blockThemes->pluck('id_theme'))
                    ->select('sbt.id_theme', 'sbt.id_exam_type')
                    ->get()
                    ->map(fn ($theme) => $theme->id_theme.'|'.$theme->id_exam_type);

                $exclusiveThemes = $blockThemes->filter(
                    fn ($theme) => ! $sharedThemePairs->contains(
                        $theme->id_theme.'|'.$theme->id_exam_type
                    )
                );

                $deletedExams = DB::table('exams')
                    ->where('id_client', $idClient)
                    ->where('id_study_block', $idStudyBlock)
                    ->delete();

                $deletedAssignments = DB::table('smart_review_assignments')
                    ->where('id_study_block', $idStudyBlock)
                    ->delete();

                $deletedThemeReviews = $exclusiveThemes->sum(
                    fn ($theme) => DB::table('student_theme_reviews')
                        ->where('id_client', $idClient)
                        ->where('id_theme', $theme->id_theme)
                        ->where('id_exam_type', $theme->id_exam_type)
                        ->delete()
                );

                $deletedThemes = DB::table('study_block_themes')
                    ->where('id_study_block', $idStudyBlock)
                    ->delete();

                DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->delete();

                return [
                    'id_study_block' => $idStudyBlock,
                    'deleted_exams' => $deletedExams,
                    'deleted_assignments' => $deletedAssignments,
                    'deleted_theme_reviews' => $deletedThemeReviews,
                    'deleted_themes' => $deletedThemes,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Bloque de repaso adaptativo eliminado correctamente.',
                'data' => $deleted,
            ]);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'BLOCK_NOT_FOUND') {
                return response()->json([
                    'status' => false,
                    'message' => 'No se encontró el bloque de repaso adaptativo.',
                ], Response::HTTP_NOT_FOUND);
            }

            Log::error('Error interno al eliminar bloque de repaso adaptativo.', [
                'id_client' => $idClient,
                'id_study_block' => $idStudyBlock,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No se pudo eliminar el bloque de repaso adaptativo.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar bloque de repaso adaptativo.', [
                'id_client' => $idClient,
                'id_study_block' => $idStudyBlock,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'No se pudo eliminar el bloque de repaso adaptativo.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/due",
     *     summary="Obtener revisiones pendientes",
     *     description="Retorna un listado ligero de las revisiones del bloque indicado, incluyendo los IDs necesarios para consultar sus preguntas en /smart-review/due/questions.",
     *     operationId="getDueSmartReviews",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id_study_block",
     *         in="query",
     *         required=true,
     *         description="ID del bloque de estudio del estudiante autenticado",
     *
     *         @OA\Schema(type="integer", minimum=1, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Revisiones pendientes obtenidas correctamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "data"},
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"total", "active_reviews", "overdue_reviews", "upcoming_reviews", "has_active_reviews", "has_overdue_reviews", "has_upcoming_reviews", "modal_type", "reviews"},
     *                 @OA\Property(property="total", type="integer", minimum=0, example=2),
     *                 @OA\Property(property="active_reviews", type="integer", minimum=0, example=1),
     *                 @OA\Property(property="overdue_reviews", type="integer", minimum=0, example=1),
     *                 @OA\Property(property="upcoming_reviews", type="integer", minimum=0, example=1),
     *                 @OA\Property(property="has_active_reviews", type="boolean", example=true),
     *                 @OA\Property(property="has_overdue_reviews", type="boolean", example=true),
     *                 @OA\Property(property="has_upcoming_reviews", type="boolean", example=true),
     *                 @OA\Property(property="modal_type", type="string", nullable=true, enum={"active", "overdue"}, example="overdue"),
     *                 @OA\Property(
     *                     property="reviews",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *                         required={"id_smart_review_assignment", "id_student_theme_review", "id_study_block", "id_theme", "theme", "id_exam_type", "initialized_at", "last_reviewed_at", "next_review_at", "scheduled_for", "review_status", "questions_available"},
     *
     *                         @OA\Property(property="id_smart_review_assignment", type="integer", nullable=true, example=50),
     *                         @OA\Property(property="id_student_theme_review", type="integer", example=24),
     *                         @OA\Property(property="id_study_block", type="integer", nullable=true, example=15),
     *                         @OA\Property(property="id_theme", type="string", format="uuid"),
     *                         @OA\Property(property="theme", type="string", example="CARDIOLOGÍA"),
     *                         @OA\Property(property="initialized_at", type="string", format="date", example="2026-09-24"),
     *                         @OA\Property(property="last_reviewed_at", type="string", format="date", nullable=true, example=null),
     *                         @OA\Property(property="next_review_at", type="string", format="date", example="2026-09-25"),
     *                         @OA\Property(property="scheduled_for", type="string", format="date", example="2026-09-25"),
     *                         @OA\Property(property="review_status", type="string", enum={"active", "overdue", "upcoming"}, example="overdue"),
     *                         @OA\Property(property="questions_available", type="boolean", example=true),
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
     *             type="object",
     *
     *             @OA\Property(property="message", type="string", example="Token no válido")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="El bloque no existe o no pertenece al estudiante"),
     *     @OA\Response(response=422, description="id_study_block es obligatorio o no es válido")
     * )
     */
    public function due(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'id_study_block' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Debes enviar un id_study_block válido.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $idStudyBlock = (int) $validated['id_study_block'];
        $idClient = (int) auth('sanctum')->user()->id_client;
        $now = now('America/Lima');
        $startOfToday = $now->copy()->startOfDay();

        $blockExists = DB::table('study_blocks')
            ->where('id_study_block', $idStudyBlock)
            ->where('id_client', $idClient)
            ->exists();

        if (! $blockExists) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontró el bloque de repaso adaptativo.',
            ], Response::HTTP_NOT_FOUND);
        }

        $reviewAssignmentsQuery = DB::table('student_theme_reviews as str')
            ->join('study_block_themes as sbt', function ($join) use ($idStudyBlock) {
                $join->on('sbt.id_theme', '=', 'str.id_theme')
                    ->on('sbt.id_exam_type', '=', 'str.id_exam_type')
                    ->where('sbt.id_study_block', $idStudyBlock);
            })
            ->leftJoin(
                'smart_review_assignments as sra',
                function ($join) use ($idStudyBlock) {
                    $join->on(
                        'sra.id_student_theme_review',
                        '=',
                        'str.id_student_theme_review'
                    )
                        ->where('sra.id_study_block', $idStudyBlock)
                        ->whereRaw(
                            'sra.scheduled_for = DATE(str.next_review_at)'
                        )
                        ->where('sra.status', '=', 'pending');
                }
            )
            ->join('themes as t', 't.id_theme', '=', 'str.id_theme')
            ->where('str.id_client', $idClient)
            ->whereNotNull('str.next_review_at')
            ->where('t.status', 1)
            ->select(
                'sra.id_smart_review_assignment',
                'sbt.id_study_block',
                'sra.scheduled_for',
                'str.id_student_theme_review',
                'str.id_theme',
                'str.id_exam_type',
                'str.initialized_at',
                'str.last_reviewed_at',
                'str.next_review_at',
                't.uuid as theme_uuid',
                't.theme'
            )
            ->orderBy('str.next_review_at');

        $reviewAssignments = $reviewAssignmentsQuery->get();

        if ($reviewAssignments->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => [
                    'total' => 0,
                    'active_reviews' => 0,
                    'overdue_reviews' => 0,
                    'upcoming_reviews' => 0,
                    'has_active_reviews' => false,
                    'has_overdue_reviews' => false,
                    'has_upcoming_reviews' => false,
                    'modal_type' => null,
                    'reviews' => [],
                ],
            ]);
        }

        $reviews = $reviewAssignments
            ->map(function ($themeReview) use ($startOfToday) {
                $scheduledFor = Carbon::parse(
                    $themeReview->next_review_at,
                    'America/Lima'
                )->startOfDay();

                return [
                    'id_smart_review_assignment' => $themeReview->id_smart_review_assignment,
                    'id_student_theme_review' => (int) $themeReview->id_student_theme_review,
                    'id_study_block' => $themeReview->id_study_block !== null
                        ? (int) $themeReview->id_study_block
                        : null,
                    'id_theme' => $themeReview->theme_uuid,
                    'theme' => $themeReview->theme,
                    'id_exam_type' => $themeReview->id_exam_type,
                    'initialized_at' => Carbon::parse(
                        $themeReview->initialized_at,
                        'America/Lima'
                    )->toDateString(),
                    'last_reviewed_at' => $themeReview->last_reviewed_at
                        ? Carbon::parse(
                            $themeReview->last_reviewed_at,
                            'America/Lima'
                        )->toDateString()
                        : null,
                    'next_review_at' => Carbon::parse(
                        $themeReview->next_review_at,
                        'America/Lima'
                    )->toDateString(),
                    'scheduled_for' => $scheduledFor->toDateString(),
                    'review_status' => $scheduledFor->lt($startOfToday)
                        ? 'overdue'
                        : ($scheduledFor->isSameDay($startOfToday)
                            ? 'active'
                            : 'upcoming'),
                    'questions_available' => $themeReview->id_smart_review_assignment !== null,
                ];
            })
            ->values();

        $overdueReviews = $reviews->where('review_status', 'overdue')->count();
        $activeReviews = $reviews->where('review_status', 'active')->count();
        $upcomingReviews = $reviews->where('review_status', 'upcoming')->count();

        return response()->json([
            'status' => true,
            'data' => [
                'total' => $reviews->count(),
                'active_reviews' => $activeReviews,
                'overdue_reviews' => $overdueReviews,
                'upcoming_reviews' => $upcomingReviews,
                'has_active_reviews' => $activeReviews > 0,
                'has_overdue_reviews' => $overdueReviews > 0,
                'has_upcoming_reviews' => $upcomingReviews > 0,
                'modal_type' => $overdueReviews > 0
                    ? 'overdue'
                    : ($activeReviews > 0 ? 'active' : null),
                'reviews' => $reviews,
            ],
        ], Response::HTTP_OK, [], self::JSON_RESPONSE_OPTIONS);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/due/questions",
     *     operationId="getDueSmartReviewQuestions",
     *     summary="Obtener las preguntas de un repaso pendiente",
     *     description="Retorna las preguntas cuando el ID de asignación y el ID de revisión de tema corresponden entre sí y pertenecen al estudiante autenticado.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="id_smart_review_assignment", in="query", required=true, @OA\Schema(type="integer", minimum=1, example=26)),
     *     @OA\Parameter(name="id_student_theme_review", in="query", required=true, @OA\Schema(type="integer", minimum=1, example=9)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Preguntas del repaso obtenidas correctamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "data"},
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"id_study_block", "id_smart_review_assignment", "id_student_theme_review", "id_theme", "theme", "id_exam_type", "scheduled_for", "questions"},
     *                 @OA\Property(property="id_study_block", type="integer", example=8),
     *                 @OA\Property(property="id_smart_review_assignment", type="integer", example=26),
     *                 @OA\Property(property="id_student_theme_review", type="integer", example=9),
     *                 @OA\Property(property="id_theme", type="string", format="uuid"),
     *                 @OA\Property(property="theme", type="string", example="Fisiología"),
     *                 @OA\Property(property="id_exam_type", type="string", example="ENAM"),
     *                 @OA\Property(property="scheduled_for", type="string", format="date", example="2026-10-01"),
     *                 @OA\Property(
     *                     property="questions",
     *                     type="array",
     *                     minItems=20,
     *                     maxItems=20,
     *
     *                     @OA\Items(
     *                         type="object",
     *                         required={"id_question", "id_theme", "theme", "id_exam_type", "question", "alternatives"},
     *
     *                         @OA\Property(property="id_question", type="integer", example=589),
     *                         @OA\Property(property="id_theme", type="string", format="uuid"),
     *                         @OA\Property(property="theme", type="string", example="Fisiología"),
     *                         @OA\Property(property="id_exam_type", type="string", example="ENAM"),
     *                         @OA\Property(property="question", type="string"),
     *                         @OA\Property(property="image", type="string", nullable=true),
     *                         @OA\Property(property="response", type="string", example="B"),
     *                         @OA\Property(property="distractor_analysis", type="string", nullable=true),
     *                         @OA\Property(property="justification", type="string", nullable=true),
     *                         @OA\Property(property="reference", type="string", nullable=true),
     *                         @OA\Property(
     *                             property="alternatives",
     *                             type="object",
     *                             required={"a", "b", "c", "d", "e"},
     *                             @OA\Property(property="a", type="string"),
     *                             @OA\Property(property="b", type="string"),
     *                             @OA\Property(property="c", type="string"),
     *                             @OA\Property(property="d", type="string"),
     *                             @OA\Property(property="e", type="string")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="La asignación no existe, los IDs no coinciden o no pertenece al estudiante"),
     *     @OA\Response(response=422, description="Los dos IDs son obligatorios y deben ser enteros positivos")
     * )
     */
    public function dueQuestions(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'id_smart_review_assignment' => ['required', 'integer', 'min:1'],
            'id_student_theme_review' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Debes enviar IDs válidos de la asignación y de la revisión del tema.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        $idClient = (int) auth('sanctum')->user()->id_client;
        $assignment = DB::table('smart_review_assignments as sra')
            ->join('student_theme_reviews as str', 'str.id_student_theme_review', '=', 'sra.id_student_theme_review')
            ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sra.id_study_block')
            ->join('themes as t', 't.id_theme', '=', 'str.id_theme')
            ->where('sra.id_smart_review_assignment', (int) $validated['id_smart_review_assignment'])
            ->where('str.id_student_theme_review', (int) $validated['id_student_theme_review'])
            ->where('str.id_client', $idClient)
            ->where('sb.id_client', $idClient)
            ->where('sra.status', 'pending')
            ->select(
                'sra.id_smart_review_assignment',
                'sra.id_study_block',
                'sra.questions',
                'sra.scheduled_for',
                'str.id_student_theme_review',
                'str.id_exam_type',
                't.uuid as theme_uuid',
                't.theme'
            )
            ->first();

        if (! $assignment) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontró la asignación de repaso solicitada.',
            ], Response::HTTP_NOT_FOUND);
        }

        $questions = collect(json_decode($assignment->questions ?? '[]', true) ?: [])
            ->map(function (array $question) use ($assignment) {
                $question['id_question'] = (int) ($question['id_question'] ?? $question['question_id']);
                $question['id_theme'] = $assignment->theme_uuid;
                $question['theme'] = $assignment->theme;
                $question['id_exam_type'] = $assignment->id_exam_type;
                unset($question['question_id']);

                return $question;
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => [
                'id_study_block' => (int) $assignment->id_study_block,
                'id_smart_review_assignment' => (int) $assignment->id_smart_review_assignment,
                'id_student_theme_review' => (int) $assignment->id_student_theme_review,
                'id_theme' => $assignment->theme_uuid,
                'theme' => $assignment->theme,
                'id_exam_type' => $assignment->id_exam_type,
                'scheduled_for' => $assignment->scheduled_for,
                'questions' => $questions,
            ],
        ], Response::HTTP_OK, [], self::JSON_RESPONSE_OPTIONS);
    }

    protected function calculateSmartReviewQuality(
        bool $isCorrect,
        string $difficulty
    ): int {
        $difficultyScore = match ($difficulty) {
            'hard' => 0,
            'regular' => 1,
            'easy' => 2,
            default => throw new \InvalidArgumentException("Dificultad inválida: {$difficulty}"),
        };

        return $difficultyScore + ($isCorrect ? 3 : 0);
    }

    protected function calculateSmartReviewSchedule(object $review, int $quality): array
    {
        $easinessFactor = (float) $review->easiness_factor;
        $repetitions = (int) $review->repetitions;
        $intervalDays = (int) $review->interval_days;
        $easinessFactor += 0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02);
        $easinessFactor = max(1.3, $easinessFactor);

        if ($quality < 3) {
            return [0, 1, $easinessFactor];
        }

        $repetitions++;
        $intervalDays = match ($repetitions) {
            1 => 1,
            2 => 6,
            default => max(1, (int) round($intervalDays * $easinessFactor)),
        };

        return [$repetitions, $intervalDays, $easinessFactor];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/smart-review/review",
     *     summary="Registrar las 20 respuestas de un repaso",
     *     description="Evalúa las 20 respuestas de una asignación, consolida la calidad con floor, actualiza SM-2 una sola vez y marca la asignación como completada.",
     *     operationId="submitSmartReview",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"title", "id_smart_review_assignment", "time_spent", "started_at", "completed_at", "answers"},
     *
     *             @OA\Property(property="title", type="string", maxLength=255, example="Repaso de cardiología"),
     *             @OA\Property(property="id_smart_review_assignment", type="integer", example=50),
     *             @OA\Property(property="time_spent", type="integer", minimum=0, example=900),
     *             @OA\Property(property="started_at", type="string", format="date-time", example="2026-09-27T10:00:00-05:00"),
     *             @OA\Property(property="completed_at", type="string", format="date-time", example="2026-09-27T10:15:00-05:00"),
     *             @OA\Property(
     *                 property="answers",
     *                 type="array",
     *                 minItems=20,
     *                 maxItems=20,
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id_question", "answer", "difficulty"},
     *
     *                     @OA\Property(property="id_question", type="integer", example=589),
     *                     @OA\Property(property="answer", type="string", enum={"A", "B", "C", "D", "E"}, example="B"),
     *                     @OA\Property(property="difficulty", type="string", enum={"hard", "regular", "easy"}, example="regular")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Repaso registrado correctamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message", "data"},
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Repaso registrado correctamente."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"id_exam", "uuid", "id_smart_review_assignment", "id_theme", "correct_answers", "total_questions", "score_percentage", "quality", "next_review_at", "results"},
     *                 @OA\Property(property="id_exam", type="integer", example=95),
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="id_smart_review_assignment", type="integer", example=50),
     *                 @OA\Property(property="id_theme", type="integer", example=24),
     *                 @OA\Property(property="correct_answers", type="integer", minimum=0, maximum=20, example=15),
     *                 @OA\Property(property="total_questions", type="integer", example=20),
     *                 @OA\Property(property="score_percentage", type="number", format="float", example=75),
     *                 @OA\Property(property="quality", type="integer", minimum=0, maximum=5, example=4),
     *                 @OA\Property(property="next_review_at", type="string", format="date-time", example="2026-10-01 14:30:00"),
     *                 @OA\Property(
     *                     property="results",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id_question", type="integer", example=589),
     *                         @OA\Property(property="student_answer", type="string", example="B"),
     *                         @OA\Property(property="correct_answer", type="string", example="B"),
     *                         @OA\Property(property="correct", type="boolean", example=true),
     *                         @OA\Property(property="difficulty", type="string", enum={"hard", "regular", "easy"}, example="regular"),
     *                         @OA\Property(property="quality", type="integer", minimum=0, maximum=5, example=4),
     *                         @OA\Property(property="justification", type="string", nullable=true),
     *                         @OA\Property(property="distractor_analysis", type="string", nullable=true),
     *                         @OA\Property(property="reference", type="string", nullable=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(
     *         response=404,
     *         description="Asignación de repaso no encontrada",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se encontró la asignación de repaso.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Asignación futura o ya completada",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Este tema todavía no corresponde repasar.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos o preguntas diferentes a la asignación",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="La pregunta no corresponde al repaso generado.")
     *         )
     *     )
     * )
     */
    public function review(Request $request)
    {
        if (is_array($request->input('answers'))) {
            $request->merge([
                'answers' => collect($request->input('answers'))
                    ->map(function ($answer) {
                        if (
                            is_array($answer)
                            && ! array_key_exists('id_question', $answer)
                            && array_key_exists('question_id', $answer)
                        ) {
                            $answer['id_question'] = $answer['question_id'];
                        }

                        return $answer;
                    })
                    ->all(),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'id_smart_review_assignment' => ['required', 'integer'],
            'time_spent' => ['required', 'integer', 'min:0'],
            'started_at' => ['required', 'date'],
            'completed_at' => ['required', 'date', 'after_or_equal:started_at'],
            'answers' => ['required', 'array', 'size:20'],
            'answers.*.id_question' => ['required', 'integer', 'distinct'],
            'answers.*.answer' => ['required', 'string', 'in:A,B,C,D,E,a,b,c,d,e'],
            'answers.*.difficulty' => ['required', 'string', 'in:hard,regular,easy'],
        ]);

        if ($validator->fails()) {
            Log::warning('Validación fallida al registrar repaso adaptativo.', [
                'id_client' => auth('sanctum')->user()?->id_client,
                'received_fields' => array_keys($request->all()),
                'answers_count' => is_array($request->input('answers'))
                    ? count($request->input('answers'))
                    : null,
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Los datos enviados para registrar el repaso no son válidos.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        $idClient = (int) auth('sanctum')->user()->id_client;
        $idAssignment = (int) $validated['id_smart_review_assignment'];
        $startedAt = Carbon::parse($validated['started_at'])
            ->setTimezone('America/Lima');
        $completedAt = Carbon::parse($validated['completed_at'])
            ->setTimezone('America/Lima');
        $answers = collect($validated['answers'])->map(fn (array $answer) => [
            'id_question' => (int) $answer['id_question'],
            'answer' => strtoupper(trim($answer['answer'])),
            'difficulty' => strtolower(trim($answer['difficulty'])),
        ]);

        try {
            $result = DB::transaction(function () use (
                $idClient,
                $idAssignment,
                $answers,
                $startedAt,
                $completedAt,
                $validated
            ) {
                $review = DB::table('smart_review_assignments as sra')
                    ->join(
                        'student_theme_reviews as str',
                        'str.id_student_theme_review',
                        '=',
                        'sra.id_student_theme_review'
                    )
                    ->where('sra.id_smart_review_assignment', $idAssignment)
                    ->where('str.id_client', $idClient)
                    ->join('themes as t', 't.id_theme', '=', 'str.id_theme')
                    ->select(
                        'sra.id_smart_review_assignment',
                        'sra.id_study_block',
                        'sra.scheduled_for',
                        'sra.questions',
                        'sra.status',
                        'str.id_student_theme_review',
                        'str.id_theme',
                        'str.repetitions',
                        'str.interval_days',
                        'str.easiness_factor',
                        'str.next_review_at',
                        't.theme'
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $review) {
                    throw new \RuntimeException('REVIEW_NOT_FOUND');
                }

                if ($review->status === 'completed') {
                    throw new \RuntimeException('REVIEW_ALREADY_COMPLETED');
                }

                $now = now('America/Lima');

                if ($now->copy()->startOfDay()->lt(Carbon::parse(
                    $review->scheduled_for,
                    'America/Lima'
                )->startOfDay())) {
                    throw new \RuntimeException('REVIEW_NOT_DUE');
                }

                $generatedQuestions = collect(
                    json_decode($review->questions, true) ?: []
                );

                if ($generatedQuestions->count() !== 20) {
                    throw new \RuntimeException('INVALID_ASSIGNMENT');
                }

                $generatedQuestionIds = $generatedQuestions
                    ->pluck('question_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();
                $submittedQuestionIds = $answers
                    ->pluck('id_question')
                    ->sort()
                    ->values();

                if ($submittedQuestionIds->all() !== $generatedQuestionIds->all()) {
                    throw new \RuntimeException('INVALID_QUESTIONS');
                }

                $answersByQuestion = $answers->keyBy('id_question');
                $correctAnswers = 0;
                $qualities = [];
                $answeredQuestions = $generatedQuestions->map(function (
                    array $question
                ) use ($answersByQuestion, &$correctAnswers, &$qualities, $completedAt) {
                    $idQuestion = (int) $question['question_id'];
                    $answer = $answersByQuestion->get($idQuestion);
                    $correctAnswer = strtoupper(trim((string) $question['response']));
                    $isCorrect = $answer['answer'] === $correctAnswer;
                    $quality = $this->calculateSmartReviewQuality(
                        $isCorrect,
                        $answer['difficulty']
                    );

                    if ($isCorrect) {
                        $correctAnswers++;
                    }
                    $qualities[] = $quality;

                    return array_merge($question, [
                        'student_answer' => $answer['answer'],
                        'difficulty' => $answer['difficulty'],
                        'correct' => $isCorrect,
                        'quality' => $quality,
                        'answered_at' => $completedAt->toDateTimeString(),
                    ]);
                })->values();

                $quality = (int) floor(collect($qualities)->avg());

                [$repetitions, $intervalDays, $easinessFactor] =
                    $this->calculateSmartReviewSchedule($review, $quality);

                $nextReviewAt = $completedAt->copy()->addDays($intervalDays);

                DB::table('student_theme_reviews')
                    ->where(
                        'id_student_theme_review',
                        $review->id_student_theme_review
                    )
                    ->update([
                        'repetitions' => $repetitions,
                        'interval_days' => $intervalDays,
                        'easiness_factor' => round($easinessFactor, 2),
                        'last_quality' => $quality,
                        'last_reviewed_at' => $completedAt,
                        'next_review_at' => $nextReviewAt,
                        'updated_at' => $now,
                    ]);

                DB::table('smart_review_assignments')
                    ->where('id_smart_review_assignment', $idAssignment)
                    ->update([
                        'questions' => json_encode(
                            $answeredQuestions,
                            JSON_UNESCAPED_UNICODE
                        ),
                        'status' => 'completed',
                        'completed_at' => $completedAt,
                        'updated_at' => $now,
                    ]);

                $examUuid = (string) Str::uuid();
                $scorePercentage = round(
                    ($correctAnswers / $answeredQuestions->count()) * 100,
                    2
                );
                $idExam = DB::table('exams')->insertGetId([
                    'id_client' => $idClient,
                    'id_study_block' => $review->id_study_block,
                    'uuid' => $examUuid,
                    'exam_type' => self::SMART_REVIEW_EXAM_TYPE,
                    'smart_review_stage' => 'review',
                    'title' => $validated['title'],
                    'total_questions' => $answeredQuestions->count(),
                    'score_percentage' => $scorePercentage,
                    'time_spent' => (int) $validated['time_spent'],
                    'exam_summary' => json_encode(
                        $answeredQuestions->map(fn ($question) => [
                            'question_id' => $question['question_id'],
                            'response' => $question['student_answer'],
                            'difficulty' => $question['difficulty'],
                            'correct' => $question['correct'],
                            'quality' => $question['quality'],
                        ])->values(),
                        JSON_UNESCAPED_UNICODE
                    ),
                    'recommendation' => '',
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'status' => 'completed',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [
                    'id_exam' => $idExam,
                    'uuid' => $examUuid,
                    'title' => $validated['title'],
                    'id_smart_review_assignment' => $idAssignment,
                    'id_theme' => $review->id_theme,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => $answeredQuestions->count(),
                    'score_percentage' => $scorePercentage,
                    'quality' => $quality,
                    'next_review_at' => $nextReviewAt->toDateTimeString(),
                    'results' => $answeredQuestions->map(fn ($question) => [
                        'id_question' => $question['question_id'],
                        'student_answer' => $question['student_answer'],
                        'correct_answer' => $question['response'],
                        'correct' => $question['correct'],
                        'difficulty' => $question['difficulty'],
                        'quality' => $question['quality'],
                        'justification' => $question['justification'],
                        'distractor_analysis' => $question['distractor_analysis'],
                        'reference' => $question['reference'],
                    ])->values(),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Repaso registrado correctamente.',
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            $knownErrors = [
                'REVIEW_NOT_FOUND',
                'REVIEW_ALREADY_COMPLETED',
                'REVIEW_NOT_DUE',
                'INVALID_ASSIGNMENT',
                'INVALID_QUESTIONS',
            ];

            if (! in_array($e->getMessage(), $knownErrors, true)) {
                Log::error('Error interno al registrar repaso adaptativo.', [
                    'id_client' => $idClient,
                    'id_smart_review_assignment' => $idAssignment,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Ocurrió un error interno al registrar el repaso.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return match ($e->getMessage()) {
                'REVIEW_NOT_FOUND' => response()->json([
                    'status' => false,
                    'message' => 'No se encontró la asignación de repaso.',
                ], Response::HTTP_NOT_FOUND),
                'REVIEW_ALREADY_COMPLETED' => response()->json([
                    'status' => false,
                    'message' => 'Esta asignación de repaso ya fue completada.',
                ], Response::HTTP_CONFLICT),
                'REVIEW_NOT_DUE' => response()->json([
                    'status' => false,
                    'message' => 'Este tema todavía no corresponde repasar.',
                ], Response::HTTP_CONFLICT),
                'INVALID_ASSIGNMENT' => response()->json([
                    'status' => false,
                    'message' => 'La asignación no contiene exactamente 20 preguntas.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),
                'INVALID_QUESTIONS' => response()->json([
                    'status' => false,
                    'message' => 'Las preguntas enviadas no corresponden a la asignación.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),
                default => throw $e,
            };
        } catch (\Throwable $e) {
            Log::error('Error inesperado al registrar repaso adaptativo.', [
                'id_client' => $idClient,
                'id_smart_review_assignment' => $idAssignment,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error interno al registrar el repaso.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/posttest",
     *     summary="Obtener el postest preparado automáticamente",
     *     description="Devuelve el postest generado por el scheduler. Si todavía no fue preparado, encola su generación y responde con estado pending.",
     *     operationId="getSmartReviewPosttest",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="idStudyBlock",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Postest listo",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id_exam", type="integer", example=81),
     *                 @OA\Property(property="uuid", type="string", format="uuid"),
     *                 @OA\Property(property="id_study_block", type="integer", example=15),
     *                 @OA\Property(property="type", type="string", example="posttest"),
     *                 @OA\Property(property="processing_status", type="string", example="ready"),
     *                 @OA\Property(property="total_questions", type="integer", example=50),
     *                 @OA\Property(
     *                     property="questions",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id_question", type="integer", example=589),
     *                         @OA\Property(property="id_theme", type="string", format="uuid"),
     *                         @OA\Property(property="theme", type="string", example="CARDIOLOGÍA"),
     *                         @OA\Property(property="id_exam_type", type="string", example="ENAM"),
     *                         @OA\Property(property="question", type="string"),
     *                         @OA\Property(property="image", type="string", nullable=true),
     *                         @OA\Property(property="response", type="string", nullable=true, example="B"),
     *                         @OA\Property(property="distractor_analysis", type="string", nullable=true),
     *                         @OA\Property(property="justification", type="string", nullable=true),
     *                         @OA\Property(property="reference", type="string", nullable=true),
     *                         @OA\Property(property="alternatives", type="object")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=202, description="Postest en preparación"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="Bloque no encontrado"),
     *     @OA\Response(response=409, description="Pretest incompleto o postest todavía no disponible")
     * )
     */
    public function generatePosttest(int $idStudyBlock)
    {
        $idClient = (int) auth('sanctum')->user()->id_client;

        $block = DB::table('study_blocks')
            ->where('id_study_block', $idStudyBlock)
            ->where('id_client', $idClient)
            ->where('status', 'active')
            ->select('pretest_completed_at', 'posttest_available_at')
            ->first();

        if (! $block) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontró el bloque de repaso.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (empty($block->pretest_completed_at)) {
            return response()->json([
                'status' => false,
                'message' => 'Primero debes completar la evaluación inicial.',
            ], Response::HTTP_CONFLICT);
        }

        $endOfToday = now('America/Lima')->endOfDay();
        $posttestAvailableAt = Carbon::parse(
            $block->posttest_available_at,
            'America/Lima'
        );
        if (empty($block->posttest_available_at) || $posttestAvailableAt->gt($endOfToday)) {
            return response()->json([
                'status' => false,
                'message' => 'La evaluación de progreso todavía no está disponible.',
                'available_at' => $block->posttest_available_at,
            ], Response::HTTP_CONFLICT);
        }

        $posttest = DB::table('exams')
            ->where('id_client', $idClient)
            ->where('id_study_block', $idStudyBlock)
            ->where('smart_review_stage', 'posttest')
            ->where('status', 'in_progress')
            ->latest('id_exam')
            ->select('id_exam', 'uuid', 'exam_summary')
            ->first();

        if (! $posttest) {
            GenerateSmartReviewPosttestJob::dispatch($idStudyBlock);

            return response()->json([
                'status' => true,
                'message' => 'La evaluación de progreso se está preparando.',
                'data' => [
                    'id_study_block' => $idStudyBlock,
                    'processing_status' => 'pending',
                ],
            ], Response::HTTP_ACCEPTED);
        }

        $questionIds = collect(json_decode($posttest->exam_summary, true))
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $questions = DB::table('questions as q')
            ->join('themes as t', 't.id_theme', '=', 'q.id_theme')
            ->whereIn('q.id_question', $questionIds)
            ->select(
                'q.id_question',
                't.uuid as theme_uuid',
                't.theme',
                'q.question',
                'q.image',
                'q.alt_a',
                'q.alt_b',
                'q.alt_c',
                'q.alt_d',
                'q.alt_e',
                'q.id_exam_type',
                'q.response',
                'q.distractor_analysis',
                'q.justification',
                'q.reference'
            )
            ->get()
            ->keyBy('id_question');

        $responseQuestions = $questionIds
            ->map(function ($idQuestion) use ($questions) {
                $question = $questions->get($idQuestion);

                if (! $question) {
                    return null;
                }

                return [
                    'id_question' => $question->id_question,
                    'id_theme' => $question->theme_uuid,
                    'theme' => $question->theme,
                    'id_exam_type' => $question->id_exam_type,
                    'question' => $question->question,
                    'image' => $question->image,
                    'response' => $question->response,
                    'distractor_analysis' => $question->distractor_analysis,
                    'justification' => $question->justification,
                    'reference' => $question->reference,
                    'alternatives' => [
                        'a' => $question->alt_a,
                        'b' => $question->alt_b,
                        'c' => $question->alt_c,
                        'd' => $question->alt_d,
                        'e' => $question->alt_e,
                    ],
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'status' => true,
            'data' => [
                'id_exam' => $posttest->id_exam,
                'uuid' => $posttest->uuid,
                'id_study_block' => $idStudyBlock,
                'type' => 'posttest',
                'processing_status' => 'ready',
                'total_questions' => $responseQuestions->count(),
                'questions' => $responseQuestions,
            ],
        ], Response::HTTP_OK, [], self::JSON_RESPONSE_OPTIONS);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/posttest/complete",
     *     summary="Completar el postest de un bloque",
     *     description="Corrige las respuestas del postest previamente generado, registra el resultado y programa el siguiente postest dentro de 28 días.",
     *     operationId="completeSmartReviewPosttest",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="idStudyBlock",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"title", "time_spent", "started_at", "completed_at", "answers"},
     *
     *             @OA\Property(property="title", type="string", maxLength=255, example="Mi evaluación de progreso"),
     *             @OA\Property(property="time_spent", type="integer", minimum=0, example=1200),
     *             @OA\Property(property="started_at", type="string", format="date-time", example="2026-10-23T14:30:00-05:00"),
     *             @OA\Property(property="completed_at", type="string", format="date-time", example="2026-10-23T14:50:00-05:00"),
     *             @OA\Property(
     *                 property="answers",
     *                 type="array",
     *                 minItems=50,
     *                 maxItems=50,
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id_question", "answer"},
     *
     *                     @OA\Property(property="id_question", type="integer", example=589),
     *                     @OA\Property(property="answer", type="string", enum={"A", "B", "C", "D", "E"}, example="B")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Postest completado correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Evaluación de progreso registrada correctamente.")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="Bloque no encontrado"),
     *     @OA\Response(response=409, description="Bloque inactivo, postest no disponible o todavía en preparación"),
     *     @OA\Response(response=422, description="Datos o preguntas inválidas")
     * )
     */
    public function completePosttest(Request $request, int $idStudyBlock)
    {
        if (is_array($request->input('answers'))) {
            $request->merge([
                'answers' => collect($request->input('answers'))
                    ->map(function ($answer) {
                        if (! is_array($answer) || array_key_exists('id_question', $answer)) {
                            return $answer;
                        }

                        $answer['id_question'] = $answer['question_id']
                            ?? $answer['id']
                            ?? $answer['idQuestion']
                            ?? null;

                        return $answer;
                    })
                    ->all(),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'time_spent' => ['required', 'integer', 'min:0'],
            'started_at' => ['required', 'date'],
            'completed_at' => ['required', 'date', 'after_or_equal:started_at'],
            'answers' => ['required', 'array', 'size:50'],
            'answers.*.id_question' => ['required', 'integer', 'distinct'],
            'answers.*.answer' => ['required', 'string', 'in:A,B,C,D,E,a,b,c,d,e'],
        ]);

        if ($validator->fails()) {
            Log::warning('Validación fallida al completar posttest.', [
                'id_client' => auth('sanctum')->user()?->id_client,
                'id_study_block' => $idStudyBlock,
                'received_fields' => array_keys($request->all()),
                'answers_count' => is_array($request->input('answers'))
                    ? count($request->input('answers'))
                    : null,
                'answer_fields' => collect($request->input('answers', []))
                    ->take(3)
                    ->map(fn ($answer) => is_array($answer) ? array_keys($answer) : [])
                    ->values()
                    ->all(),
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Los datos enviados para completar el posttest no son válidos.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();

        $idClient = (int) auth('sanctum')->user()->id_client;
        $answers = collect($validated['answers'])->map(fn (array $answer) => [
            'id_question' => (int) $answer['id_question'],
            'answer' => strtoupper(trim($answer['answer'])),
        ]);

        try {
            $isLatestBlock = DB::transaction(function () use (
                $idClient,
                $idStudyBlock,
                $validated,
                $answers
            ) {
                $block = DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->select(
                        'status',
                        'pretest_completed_at',
                        'posttest_available_at'
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $block) {
                    throw new \RuntimeException('BLOCK_NOT_FOUND');
                }

                if ($block->status !== 'active') {
                    throw new \RuntimeException('BLOCK_NOT_ACTIVE');
                }

                if (empty($block->pretest_completed_at)) {
                    throw new \RuntimeException('PRETEST_REQUIRED');
                }

                $endOfToday = now('America/Lima')->endOfDay();

                if (empty($block->posttest_available_at) || Carbon::parse($block->posttest_available_at, 'America/Lima')->gt($endOfToday)) {
                    throw new \RuntimeException('POSTTEST_NOT_AVAILABLE');
                }

                $posttest = DB::table('exams')
                    ->where('id_client', $idClient)
                    ->where('id_study_block', $idStudyBlock)
                    ->where('smart_review_stage', 'posttest')
                    ->where('status', 'in_progress')
                    ->latest('id_exam')
                    ->select('id_exam', 'exam_summary')
                    ->lockForUpdate()
                    ->first();

                if (! $posttest) {
                    throw new \RuntimeException('POSTTEST_NOT_GENERATED');
                }

                $generatedQuestionIds = collect(json_decode($posttest->exam_summary, true) ?: [])
                    ->pluck('question_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();

                $submittedQuestionIds = $answers
                    ->pluck('id_question')
                    ->sort()
                    ->values();

                if ($submittedQuestionIds->all() !== $generatedQuestionIds->all()) {
                    throw new \RuntimeException('INVALID_QUESTIONS');
                }

                $questions = DB::table('questions')
                    ->whereIn('id_question', $generatedQuestionIds)
                    ->select('id_question', 'response')
                    ->get()
                    ->keyBy('id_question');

                if ($questions->count() !== $generatedQuestionIds->count()) {
                    throw new \RuntimeException('INVALID_QUESTIONS');
                }

                $correctAnswers = 0;
                $examSummary = $answers->map(function ($answer) use (
                    $questions,
                    &$correctAnswers
                ) {
                    $correctAnswer = strtoupper(
                        trim((string) $questions->get($answer['id_question'])->response)
                    );
                    $isCorrect = $answer['answer'] === $correctAnswer;

                    if ($isCorrect) {
                        $correctAnswers++;
                    }

                    return [
                        'question_id' => $answer['id_question'],
                        'response' => $answer['answer'],
                        'correct' => $isCorrect,
                    ];
                })->all();

                $scorePercentage = round(($correctAnswers / $answers->count()) * 100, 2);
                $startedAt = Carbon::parse($validated['started_at'])
                    ->setTimezone('America/Lima');
                $completedAt = Carbon::parse($validated['completed_at'])
                    ->setTimezone('America/Lima');
                $nextPosttestAt = $completedAt->copy()->addDays(28);
                $latestBlockId = DB::table('study_blocks')
                    ->where('id_client', $idClient)
                    ->orderByDesc('id_study_block')
                    ->value('id_study_block');
                $isLatestBlock = (int) $latestBlockId === $idStudyBlock;

                DB::table('exams')
                    ->where('id_exam', $posttest->id_exam)
                    ->update([
                        'title' => $validated['title'],
                        'score_percentage' => $scorePercentage,
                        'time_spent' => (int) $validated['time_spent'],
                        'exam_summary' => json_encode($examSummary, JSON_UNESCAPED_UNICODE),
                        'recommendation' => '',
                        'status' => 'completed',
                        'started_at' => $startedAt,
                        'completed_at' => $completedAt,
                        'updated_at' => now(),
                    ]);

                $blockUpdate = [
                    'posttest_completed_at' => $completedAt,
                    'posttest_available_at' => $nextPosttestAt,
                    'updated_at' => now(),
                ];

                DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->update($blockUpdate);

                return $isLatestBlock;
            });

            if ($isLatestBlock) {
                try {
                    GoogleQueue::sendQueue([
                        'value' => [
                            'type' => 6,
                            'id_client' => $idClient,
                            'id_study_block' => $idStudyBlock,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::error('No se pudo publicar la notificación de desbloqueo en Google Pub/Sub.', [
                        'id_client' => $idClient,
                        'id_study_block' => $idStudyBlock,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Evaluación de progreso registrada correctamente.',
            ]);
        } catch (\RuntimeException $e) {
            $knownErrors = [
                'BLOCK_NOT_FOUND',
                'BLOCK_NOT_ACTIVE',
                'PRETEST_REQUIRED',
                'POSTTEST_NOT_AVAILABLE',
                'POSTTEST_NOT_GENERATED',
                'INVALID_QUESTIONS',
            ];

            if (! in_array($e->getMessage(), $knownErrors, true)) {
                Log::error('Error interno al completar posttest.', [
                    'id_client' => $idClient,
                    'id_study_block' => $idStudyBlock,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Ocurrió un error interno al completar el posttest.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return match ($e->getMessage()) {
                'BLOCK_NOT_FOUND' => response()->json([
                    'status' => false,
                    'message' => 'No se encontró el bloque.',
                ], Response::HTTP_NOT_FOUND),
                'BLOCK_NOT_ACTIVE' => response()->json([
                    'status' => false,
                    'message' => 'El bloque no está activo.',
                ], Response::HTTP_CONFLICT),
                'PRETEST_REQUIRED' => response()->json([
                    'status' => false,
                    'message' => 'Primero debes completar la evaluación inicial.',
                ], Response::HTTP_CONFLICT),
                'POSTTEST_NOT_AVAILABLE' => response()->json([
                    'status' => false,
                    'message' => 'La evaluación de progreso todavía no está disponible.',
                ], Response::HTTP_CONFLICT),
                'POSTTEST_NOT_GENERATED' => response()->json([
                    'status' => false,
                    'message' => 'La evaluación de progreso todavía se está preparando.',
                ], Response::HTTP_CONFLICT),
                'INVALID_QUESTIONS' => response()->json([
                    'status' => false,
                    'message' => 'Las preguntas enviadas no corresponden al postest generado.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY),
                default => throw $e,
            };
        } catch (\Throwable $e) {
            Log::error('Error inesperado al completar posttest.', [
                'id_client' => $idClient,
                'id_study_block' => $idStudyBlock,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Ocurrió un error interno al completar el posttest.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
