<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Data\SmartReviewResultData;
use App\Exceptions\InsufficientStratifiedQuestionsException;
use App\Exceptions\SmartReviewException;
use App\Http\Requests\CompleteSmartReviewPosttestRequest;
use App\Http\Requests\CompleteSmartReviewPretestRequest;
use App\Http\Requests\CreateSmartReviewBlockRequest;
use App\Http\Requests\SmartReviewDueQuestionsRequest;
use App\Http\Requests\SmartReviewDueRequest;
use App\Http\Requests\SmartReviewExamListRequest;
use App\Http\Requests\SmartReviewThemesRequest;
use App\Http\Requests\SubmitSmartReviewRequest;
use App\Http\Resources\SmartReviewAssignmentQuestionsResource;
use App\Http\Resources\SmartReviewBlockResource;
use App\Http\Resources\SmartReviewDueResource;
use App\Http\Resources\SmartReviewQuestionResource;
use App\Http\Resources\SmartReviewResultResource;
use App\Http\Resources\SmartReviewThemeResource;
use App\Services\GoogleQueue;
use App\Services\SmartReviewExamService;
use App\Services\SmartReviewScheduleService;
use App\Services\StratifiedQuestionSelector;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SmartReviewController extends Controller
{
    private const SMART_REVIEW_EXAM_TYPE = 'smart review';

    private const MIN_THEMES_PER_BLOCK = 4;

    private const MAX_THEMES_PER_BLOCK = 20;

    private const QUESTIONS_PER_ASSESSMENT = 50;

    private const MIN_QUESTIONS_PER_THEME = 50;

    public function __construct(
        private readonly SmartReviewExamService $examService,
        private readonly SmartReviewScheduleService $scheduleService
    ) {}

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
    public function listThemesAdaptativeReview(SmartReviewThemesRequest $request)
    {
        $language = $request->query('lang');

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
            $response = SmartReviewThemeResource::collection($themes)->resolve($request);

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
     *         description="Datos inválidos, temas no disponibles, con preguntas insuficientes o utilizados previamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"status", "message"},
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No hay suficientes preguntas para los temas: Cardiología, Pediatría."),
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
    public function createStoreBlock(CreateSmartReviewBlockRequest $request)
    {
        $language = $request->query('lang');
        $idClient = auth('sanctum')->user()->id_client;

        try {
            $result = DB::transaction(
                function () use ($request, $idClient, $language) {
                    $selections = collect($request->input('themes'));
                    $duplicatePair = $selections->map(
                        fn ($item) => $item['id_theme'].'|'.$item['id_exam_type']
                    )->duplicates()->isNotEmpty();
                    if ($duplicatePair) {
                        throw new SmartReviewException('duplicateThemeExamType',Response::HTTP_UNPROCESSABLE_ENTITY,$language);
                    }
                    $lastBlock = DB::table('study_blocks')
                        ->where('id_client', $idClient)
                        ->select('posttest_completed_at')
                        ->orderByDesc('id_study_block')
                        ->lockForUpdate()
                        ->first();

                    if ($lastBlock && is_null($lastBlock->posttest_completed_at)) {
                        throw new SmartReviewException('firstPosttestRequired',Response::HTTP_CONFLICT,$language);
                    }

                    $themes = DB::table('themes as t')
                        ->whereIn('t.uuid', $selections->pluck('id_theme'))
                        ->where('t.status', 1)
                        ->select('t.id_theme', 't.uuid', 't.theme')
                        ->get()
                        ->keyBy('uuid');

                    if ($selections->contains(fn ($item) => ! $themes->has($item['id_theme']))) {
                        throw new SmartReviewException('invalidSmartReviewThemes',Response::HTTP_UNPROCESSABLE_ENTITY,$language);
                    }

                    foreach ($selections as $selection) {
                        $idTheme = $themes->get($selection['id_theme'])->id_theme;
                        $idExamType = $selection['id_exam_type'];
                        $availableQuestions = DB::table('questions')
                            ->where('id_theme', $idTheme)
                            ->where('id_exam_type', $idExamType)
                            ->where('status', 1)
                            ->count();

                        if ($availableQuestions < self::MIN_QUESTIONS_PER_THEME) {
                            throw new SmartReviewException(
                                'insufficientThemeQuestions',
                                Response::HTTP_UNPROCESSABLE_ENTITY,
                                $language
                            );
                        }
                    }

                    foreach ($selections as $selection) {
                        $idTheme = $themes->get($selection['id_theme'])->id_theme;
                        $idExamType = $selection['id_exam_type'];
                        $used = DB::table('study_block_themes as sbt')
                            ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sbt.id_study_block')
                            ->where('sb.id_client', $idClient)->where('sbt.id_theme', $idTheme)
                            ->where('sbt.id_exam_type', $idExamType)->exists();
                        if ($used) {
                            throw new SmartReviewException('usedSmartReviewThemes',Response::HTTP_UNPROCESSABLE_ENTITY,$language);
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
        } catch (SmartReviewException $e) {
            return $e->response();
        } catch (\Throwable $e) {

            Log::error('storeBlock error', [
                'id_client' => $idClient,
                'error' => $e->getMessage(),
            ]);

            return CustomResponse::responseMessage('createSmartReviewBlockError',Response::HTTP_INTERNAL_SERVER_ERROR,$language);
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
    public function generatePretest(Request $request, int $id)
    {
        $language = $request->query('lang');
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
                return CustomResponse::responseMessage('smartReviewBlockNotFound',Response::HTTP_NOT_FOUND,$language);
            }

            if ($block->pretest_completed_at !== null) {
                return CustomResponse::responseMessage('pretestAlreadyCompleted',Response::HTTP_CONFLICT,$language);
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
                return CustomResponse::responseMessage('invalidSmartReviewThemeCount',Response::HTTP_UNPROCESSABLE_ENTITY,$language);
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
                ->map(fn ($question) => (new SmartReviewQuestionResource($question))
                    ->resolve($request))
                ->shuffle()
                ->values();

            return CustomResponse::responseBody([
                'id_study_block' => $block->id_study_block,
                'questions' => $responseQuestions,
            ], Response::HTTP_OK);
        } catch (InsufficientStratifiedQuestionsException) {
            return CustomResponse::responseMessage('insufficientThemeQuestions',Response::HTTP_UNPROCESSABLE_ENTITY,$language);
        } catch (\Throwable $th) {
            Log::error(
                'Error generatePretest',
                [
                    'id_client' => $idClient,

                    'id_study_block' => $id,

                    'error' => $th->getMessage(),
                ]
            );

            return CustomResponse::responseMessage('generatePretestError',Response::HTTP_INTERNAL_SERVER_ERROR,$language);
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
        CompleteSmartReviewPretestRequest $request,
        int $idStudyBlock
    ) {
        $language = $request->query('lang');
        $idClient = (int) auth('sanctum')->user()->id_client;
        $examSummary = collect($request->answers)
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
                $request,
                $examSummary,
                $language
            ) {
                $block = DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->select('status', 'pretest_completed_at')
                    ->lockForUpdate()
                    ->first();

                if (! $block) {
                    throw new SmartReviewException(
                        'smartReviewBlockNotFound',
                        Response::HTTP_NOT_FOUND,
                        $language
                    );
                }

                if ($block->status !== 'active') {
                    throw new SmartReviewException(
                        'smartReviewBlockInactive',
                        Response::HTTP_CONFLICT,
                        $language
                    );
                }

                if ($block->pretest_completed_at !== null) {
                    throw new SmartReviewException(
                        'pretestAlreadyCompleted',
                        Response::HTTP_CONFLICT,
                        $language
                    );
                }

                $now = now();
                $startedAt = Carbon::parse($request->started_at)
                    ->setTimezone('America/Lima');
                $completedAt = Carbon::parse($request->completed_at)
                    ->setTimezone('America/Lima');
                $examUuid = (string) Str::uuid();
                $idExam = DB::table('exams')->insertGetId([
                    'id_client' => $idClient,
                    'id_study_block' => $idStudyBlock,
                    'uuid' => $examUuid,
                    'exam_type' => self::SMART_REVIEW_EXAM_TYPE,
                    'smart_review_stage' => 'pretest',
                    'title' => $request->title,
                    'total_questions' => count($examSummary),
                    'score_percentage' => (float) $request->score_percentage,
                    'time_spent' => (int) $request->time_spent,
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
                    'title' => $request->title,
                    'id_study_block' => $idStudyBlock,
                    'exam_type' => self::SMART_REVIEW_EXAM_TYPE,
                    'score_percentage' => (float) $request->score_percentage,
                    'processing_status' => 'pending',
                    'pretest_completed_at' => $completedAt->toDateTimeString(),
                    'posttest_available_at' => $posttestAvailableAt->toDateTimeString(),
                ];
            });
                GoogleQueue::sendQueue([
                    'value' => [
                        'type' => 5,
                        'id_client' => $idClient,
                        'id_study_block' => (int) $result['id_study_block'],
                        'id_exam' => (int) $result['id_exam'],
                    ],
                ]);

            return CustomResponse::responseBody(['data' => $result], Response::HTTP_ACCEPTED);
        } catch (SmartReviewException $e) {
            return $e->response();
        } catch (\Throwable $e) {
            Log::error('Error completePretest', [
                'id_client' => $idClient,
                'id_study_block' => $idStudyBlock,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return CustomResponse::responseMessage(
                'completePretestError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $language
            );
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
    public function showSmartReview(Request $request)
    {
        $language = $request->query('lang');

        try {
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

        if ($blocks->isEmpty()) {
            return CustomResponse::responseMessage(
                'smartReviewBlocksNotFound',
                Response::HTTP_NOT_FOUND,
                $language
            );
        }

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
                ? CustomResponse::translatedMessage(
                    'smartReviewThemeSelectionAvailable',
                    $language
                )
                : CustomResponse::translatedMessage(
                    'smartReviewThemeSelectionBlocked',
                    $language
                ),
        ];

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
                $request,
                $now,
                $themesByBlock,
                $examMetadataByBlock,
                $dueReviewsByBlock,
                $reviewProgressByBlock
            ) {
                return (new SmartReviewBlockResource([
                    'block' => $block,
                    'exams' => $examMetadataByBlock->get(
                        $block->id_study_block,
                        collect()
                    ),
                    'themes' => $themesByBlock->get(
                        $block->id_study_block,
                        collect()
                    ),
                    'progress' => $reviewProgressByBlock->get($block->id_study_block),
                    'due_reviews' => (int) $dueReviewsByBlock->get(
                        $block->id_study_block,
                        0
                    ),
                    'now' => $now,
                ]))->resolve($request);
            }
        )->values();

            return CustomResponse::responseBody([
                'data' => array_merge([
                    'has_smart_review' => true,
                    'blocks' => $data,
                ], $themeSelectionState),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar el repaso adaptativo.', [
                'id_client' => $idClient ?? null,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return CustomResponse::responseMessage(
                'showSmartReviewError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $language
            );
        }
    }

    private function listBlockExams(
        SmartReviewExamListRequest $request,
        int $idStudyBlock,
        string $stage
    ) {
        $language = $request->query('lang');
        $idClient = (int) auth('sanctum')->user()->id_client;

        if (! $this->examService->blockBelongsToClient($idStudyBlock, $idClient)) {
            return CustomResponse::responseMessage(
                'smartReviewBlockNotFound',
                Response::HTTP_NOT_FOUND,
                $language
            );
        }

        $exams = $this->examService->paginateCompleted(
            $idClient,
            $idStudyBlock,
            $stage,
            (int) $request->query('limit')
        );

        return CustomResponse::responseBody($exams, Response::HTTP_OK);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/pretests",
     *     operationId="getSmartReviewBlockPretest",
     *     summary="Listar pretests completados de un bloque",
     *     description="Retorna los pretests completados del bloque con paginación.",
     *     tags={"Smart Review"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="idStudyBlock", in="path", required=true, description="ID del bloque de estudio", @OA\Schema(type="integer", minimum=1, example=8)),
     *     @OA\Parameter(name="page", in="query", required=true, description="Número de página", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="limit", in="query", required=true, description="Cantidad de resultados por página", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *
     *     @OA\Response(response=200, description="Pretests paginados"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=404, description="Bloque no encontrado"),
     *     @OA\Response(response=422, description="Faltan page o limit, o sus valores no son válidos")
     * )
     */
    public function listBlockPretests(SmartReviewExamListRequest $request, int $idStudyBlock)
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
    public function listBlockReviews(SmartReviewExamListRequest $request, int $idStudyBlock)
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
    public function listBlockPosttests(SmartReviewExamListRequest $request, int $idStudyBlock)
    {
        return $this->listBlockExams($request, $idStudyBlock, 'posttest');
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
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="Bloque de repaso adaptativo eliminado correctamente.")
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
    public function deleteStudyBlock(Request $request, int $idStudyBlock)
    {
        $language = $request->query('lang');
        $idClient = (int) auth('sanctum')->user()->id_client;

        try {
            DB::transaction(function () use ($idClient, $idStudyBlock, $language) {
                $block = DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->lockForUpdate()
                    ->first();

                if (! $block) {
                    throw new SmartReviewException(
                        'smartReviewBlockNotFound',
                        Response::HTTP_NOT_FOUND,
                        $language
                    );
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

                DB::table('exams')
                    ->where('id_client', $idClient)
                    ->where('id_study_block', $idStudyBlock)
                    ->delete();

                DB::table('smart_review_assignments')
                    ->where('id_study_block', $idStudyBlock)
                    ->delete();

                $exclusiveThemes->each(
                    fn ($theme) => DB::table('student_theme_reviews')
                        ->where('id_client', $idClient)
                        ->where('id_theme', $theme->id_theme)
                        ->where('id_exam_type', $theme->id_exam_type)
                        ->delete()
                );

                DB::table('study_block_themes')
                    ->where('id_study_block', $idStudyBlock)
                    ->delete();

                DB::table('study_blocks')
                    ->where('id_study_block', $idStudyBlock)
                    ->where('id_client', $idClient)
                    ->delete();

            });

            return CustomResponse::responseMessage(
                'smartReviewBlockDeleted',
                Response::HTTP_OK,
                $language
            );
        } catch (SmartReviewException $e) {
            return $e->response();
        } catch (\Throwable $e) {
            Log::error('Error inesperado al eliminar bloque de repaso adaptativo.', [
                'id_client' => $idClient,
                'id_study_block' => $idStudyBlock,
                'error' => $e->getMessage()
            ]);

            return CustomResponse::responseMessage(
                'deleteSmartReviewBlockError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $language
            );
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
    public function due(SmartReviewDueRequest $request)
    {
        $language = $request->query('lang');
        $idStudyBlock = (int) $request->id_study_block;
        $idClient = (int) auth('sanctum')->user()->id_client;

        $blockExists = DB::table('study_blocks')
            ->where('id_study_block', $idStudyBlock)
            ->where('id_client', $idClient)
            ->exists();

        if (! $blockExists) {
            return CustomResponse::responseMessage(
                'smartReviewBlockNotFound',
                Response::HTTP_NOT_FOUND,
                $language
            );
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
            return CustomResponse::responseMessage(
                'smartReviewAssignmentsNotFound',
                Response::HTTP_NOT_FOUND,
                $language
            );
        }

        $reviews = collect(
            SmartReviewDueResource::collection($reviewAssignments)->resolve($request)
        );

        $overdueReviews = $reviews->where('review_status', 'overdue')->count();
        $activeReviews = $reviews->where('review_status', 'active')->count();
        $upcomingReviews = $reviews->where('review_status', 'upcoming')->count();

        return CustomResponse::responseBody([
            'data' => [
                'total' => $reviews->count(),
                'active_reviews' => $activeReviews,
                'overdue_reviews' => $overdueReviews,
                'upcoming_reviews' => $upcomingReviews,
                'has_active_reviews' => $activeReviews > 0,
                'has_overdue_reviews' => $overdueReviews > 0,
                'has_upcoming_reviews' => $upcomingReviews > 0,
                'modal_type' => match (true) {
                    $overdueReviews > 0 => 'overdue',
                    $activeReviews > 0 => 'active',
                    default => null,
                },
                'reviews' => $reviews,
            ],
        ], Response::HTTP_OK);
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
    public function dueQuestions(SmartReviewDueQuestionsRequest $request)
    {
        $language = $request->query('lang');
        $idClient = (int) auth('sanctum')->user()->id_client;
        $assignment = DB::table('smart_review_assignments as sra')
            ->join('student_theme_reviews as str', 'str.id_student_theme_review', '=', 'sra.id_student_theme_review')
            ->join('study_blocks as sb', 'sb.id_study_block', '=', 'sra.id_study_block')
            ->join('themes as t', 't.id_theme', '=', 'str.id_theme')
            ->where('sra.id_smart_review_assignment', (int) $request->id_smart_review_assignment)
            ->where('str.id_student_theme_review', (int) $request->id_student_theme_review)
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
            return CustomResponse::responseMessage(
                'reviewAssignmentNotFound',
                Response::HTTP_NOT_FOUND,
                $language
            );
        }

        return CustomResponse::responseBody([
            'data' => (new SmartReviewAssignmentQuestionsResource($assignment))
                ->resolve($request),
        ], Response::HTTP_OK);
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
    public function review(SubmitSmartReviewRequest $request)
    {
        $language = $request->query('lang');

        $idClient = (int) auth('sanctum')->user()->id_client;
        $idAssignment = (int) $request->id_smart_review_assignment;
        $startedAt = Carbon::parse($request->started_at)
            ->setTimezone('America/Lima');
        $completedAt = Carbon::parse($request->completed_at)
            ->setTimezone('America/Lima');
        $answers = collect($request->answers)->map(fn (array $answer) => [
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
                $request,
                $language
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
                    throw new SmartReviewException(
                        'reviewAssignmentNotFound',
                        Response::HTTP_NOT_FOUND,
                        $language
                    );
                }

                if ($review->status === 'completed') {
                    throw new SmartReviewException(
                        'reviewAlreadyCompleted',
                        Response::HTTP_CONFLICT,
                        $language
                    );
                }

                $now = now('America/Lima');

                if ($now->copy()->startOfDay()->lt(Carbon::parse(
                    $review->scheduled_for,
                    'America/Lima'
                )->startOfDay())) {
                    throw new SmartReviewException(
                        'reviewNotDue',
                        Response::HTTP_CONFLICT,
                        $language
                    );
                }

                $generatedQuestions = collect(
                    json_decode($review->questions, true) ?: []
                );

                if ($generatedQuestions->count() !== 20) {
                    throw new SmartReviewException(
                        'reviewInvalidQuestionCount',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        $language
                    );
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
                    throw new SmartReviewException(
                        'reviewQuestionsMismatch',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        $language
                    );
                }

                $answersByQuestion = $answers->keyBy('id_question');
                $correctAnswers = 0;
                $qualities = [];
                $answeredQuestions = $generatedQuestions->map(function (
                    array $question
                ) use ($answersByQuestion, &$correctAnswers, &$qualities, $completedAt, $language) {
                    $idQuestion = (int) $question['question_id'];
                    $answer = $answersByQuestion->get($idQuestion);
                    $correctAnswer = strtoupper(trim((string) $question['response']));
                    $isCorrect = $answer['answer'] === $correctAnswer;
                    $quality = $this->scheduleService->calculateQuality(
                        $isCorrect,
                        $answer['difficulty'],
                        $language
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
                    $this->scheduleService->calculateSchedule($review, $quality);

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
                    'title' => $request->title,
                    'total_questions' => $answeredQuestions->count(),
                    'score_percentage' => $scorePercentage,
                    'time_spent' => (int) $request->time_spent,
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

                return new SmartReviewResultData(
                    idExam: $idExam,
                    uuid: $examUuid,
                    title: $request->title,
                    idAssignment: $idAssignment,
                    idTheme: (int) $review->id_theme,
                    correctAnswers: $correctAnswers,
                    scorePercentage: $scorePercentage,
                    quality: $quality,
                    nextReviewAt: $nextReviewAt,
                    answeredQuestions: $answeredQuestions
                );
            });

            return CustomResponse::responseBody([
                'data' => (new SmartReviewResultResource($result))->resolve($request),
            ]);
        } catch (SmartReviewException $e) {
            return $e->response();
        } catch (\Throwable $e) {
            Log::error('Error inesperado al registrar repaso adaptativo.', [
                'id_client' => $idClient,
                'id_smart_review_assignment' => $idAssignment,
                'error' => $e->getMessage()
            ]);

            return CustomResponse::responseMessage(
                'registerSmartReviewError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $language
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/smart-review/blocks/{idStudyBlock}/posttest",
     *     summary="Obtener el postest preparado automáticamente",
     *     description="Devuelve el postest generado por el scheduler. Si todavía no fue preparado, responde con un mensaje indicando que continúa en preparación.",
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
     *     @OA\Response(response=401, description="No autorizado")
     * )
     */
    public function generatePosttest(Request $request, int $idStudyBlock)
    {
        $language = $request->query('lang');
        $idClient = (int) auth('sanctum')->user()->id_client;
        $posttest = $this->examService->findPendingPosttest($idClient, $idStudyBlock);

        if ($posttest === null) {
            return CustomResponse::responseMessage(
                'posttestPreparing',
                Response::HTTP_ACCEPTED,
                $language
            );
        }

        return CustomResponse::responseBody([
            'data' => $posttest,
        ], Response::HTTP_OK);
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
    public function completePosttest(CompleteSmartReviewPosttestRequest $request, int $idStudyBlock)
    {
        $language = $request->query('lang');

        $idClient = (int) auth('sanctum')->user()->id_client;
        $answers = collect($request->answers)->map(fn (array $answer) => [
            'id_question' => (int) $answer['id_question'],
            'answer' => strtoupper(trim($answer['answer'])),
        ]);

        try {
            $isLatestBlock = DB::transaction(function () use (
                $idClient,
                $idStudyBlock,
                $request,
                $answers,
                $language
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
                    throw new SmartReviewException(
                        'smartReviewBlockNotFound',
                        Response::HTTP_NOT_FOUND,
                        $language
                    );
                }

                if ($block->status !== 'active') {
                    throw new SmartReviewException(
                        'smartReviewBlockInactive',
                        Response::HTTP_CONFLICT,
                        $language
                    );
                }

                if (empty($block->pretest_completed_at)) {
                    throw new SmartReviewException(
                        'pretestRequired',
                        Response::HTTP_CONFLICT,
                        $language
                    );
                }

                $endOfToday = now('America/Lima')->endOfDay();

                if (empty($block->posttest_available_at) || Carbon::parse($block->posttest_available_at, 'America/Lima')->gt($endOfToday)) {
                    throw new SmartReviewException(
                        'posttestNotAvailable',
                        Response::HTTP_CONFLICT,
                        $language
                    );
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
                    throw new SmartReviewException(
                        'posttestNotGenerated',
                        Response::HTTP_CONFLICT,
                        $language
                    );
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
                    throw new SmartReviewException(
                        'posttestQuestionsMismatch',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        $language
                    );
                }

                $questions = DB::table('questions')
                    ->whereIn('id_question', $generatedQuestionIds)
                    ->select('id_question', 'response')
                    ->get()
                    ->keyBy('id_question');

                if ($questions->count() !== $generatedQuestionIds->count()) {
                    throw new SmartReviewException(
                        'posttestQuestionsMismatch',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        $language
                    );
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
                $startedAt = Carbon::parse($request->started_at)
                    ->setTimezone('America/Lima');
                $completedAt = Carbon::parse($request->completed_at)
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
                        'title' => $request->title,
                        'score_percentage' => $scorePercentage,
                        'time_spent' => (int) $request->time_spent,
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
                    GoogleQueue::sendQueue([
                        'value' => [
                            'type' => 6,
                            'id_client' => $idClient,
                            'id_study_block' => $idStudyBlock,
                        ],
                    ]);
            }

            return CustomResponse::responseMessage(
                'progressAssessmentRegistered',
                Response::HTTP_OK,
                $language
            );
        } catch (SmartReviewException $e) {
            return $e->response();
        } catch (\Throwable $e) {
            Log::error('Error inesperado al completar posttest.', [
                'id_client' => $idClient,
                'id_study_block' => $idStudyBlock,
                'error' => $e->getMessage(),
            ]);

            return CustomResponse::responseMessage(
                'completePosttestError',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $language
            );
        }
    }
}
