<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\DownloadExamRerquest;
use App\Http\Requests\ExamTypeRequest;
use App\Http\Requests\ExamUserRequest;
use App\Http\Requests\HistoryRequest;
use App\Http\Requests\LanguageRequest;
use App\Http\Requests\QuestionByThemeRequest;
use App\Http\Requests\QuestionRequest;
use App\Http\Requests\RankingRequest;
use App\Http\Requests\RegisterExamRequest;
use App\Http\Requests\ReportRequest;
use App\Http\Requests\UpdateExamStatusRequest;
use App\Models\Client;
use App\Models\Exam;
use App\Models\History;
use App\Models\Question;
use App\Models\Report;
use App\Services\GoogleQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class QuestionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/quiz/questions",
     *     summary="Obtener grupo de preguntas aleatorias",
     *     tags={"Quiz"},
     *     description="Retorna un grupo de preguntas aleatorias basadas en los parámetros proporcionados",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"specialty", "year", "exam", "count"},
     *
     *             @OA\Property(
     *                 property="specialty",
     *                 type="integer",
     *                 example=1,
     *                 description="Identidicador de la especialidad"
     *             ),
     *             @OA\Property(
     *                 property="theme",
     *                 type="string",
     *                 example="ab49bef0-7d30-11f0-88cc-0200fd8286ac",
     *                 description="Identidicador del tema"
     *             ),
     *             @OA\Property(
     *                 property="year",
     *                 type="array",
     *
     *                 @OA\Items(type="string",example="2011"),
     *                 description="Años de las preguntas"
     *             ),
     *
     *             @OA\Property(
     *                 property="exam",
     *                 type="string",
     *                 example="ENAM",
     *                 description="Tipo de examen"
     *             ),
     *             @OA\Property(
     *                 property="count",
     *                 type="integer",
     *                 example="179",
     *                 description="Número de preguntas a retornar"
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=200,
     *     description="Preguntas obtenidas exitosamente",
     *
     *     @OA\JsonContent(
     *         type="array",
     *
     *         @OA\Items(
     *             type="object",
     *
     *             @OA\Property(property="specialtyId", type="integer", example=1),
     *             @OA\Property(property="questionId", type="integer", example=589),
     *             @OA\Property(property="theme", type="string", example="FRACTURAS ENAM"),
     *             @OA\Property(property="specialty", type="string", example="ENAM"),
     *             @OA\Property(property="question", type="string", example="ENAM 2013 - Paciente que sufre traumatismo maxilofacial con lesión del etmoides ¿Qué tipo de fractura presenta?"),
     *             @OA\Property(property="image", type="string", example=""),
     *             @OA\Property(property="comment", type="string", example=""),
     *             @OA\Property(property="image_comment", type="string", example=""),
     *             @OA\Property(
     *                 property="options",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="optionId", type="string", example="a"),
     *                     @OA\Property(property="option", type="string", example="Le fort II")
     *                 )
     *             ),
     *             @OA\Property(property="data", type="string", example="eyJpdiI6IlN3WXNlWkU3UUpVYkxBYWQiLCJ2YWx1ZSI6IkFnPT0iLCJtYWMiOiIiLCJ0YWciOiIwV3Q4bWszMUFrVWdlYXRhTklRUTRnPT0ifQ==")
     *         )
     *     )
     * ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token no válido"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="No se encontraron preguntas",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No se encontraron registros")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function questionGroup(QuestionRequest $request)
    {
        $language = $request->query('lang');
        try {
            $body = [];
            $questions = Question::query()->where('questions.status', '1')
                ->join('themes', 'questions.id_theme', '=', 'themes.id_theme')
                ->join('specialties', 'themes.id_specialty', '=', 'specialties.id_specialty')
                ->when(! empty($request->specialty), function ($query) use ($request) {
                    $query->where('specialties.id_specialty', $request->specialty);
                })
                ->when(! empty($request->theme), function ($query) use ($request) {
                    $query->where('themes.uuid', $request->theme);
                })
                ->when(! empty($request->year), function ($query) use ($request) {
                    $query->whereIn('questions.year', $request->year);
                })
                ->where('questions.id_exam_type', $request->exam)
                ->inRandomOrder()->limit($request->count)
                ->select(
                    'questions.id_question',
                    'themes.theme as theme',
                    'specialties.specialty',
                    'specialties.id_specialty',
                    'questions.question',
                    'questions.comment',
                    'questions.image_comment',
                    'questions.image as questionImage',
                    'questions.alt_a as a',
                    'questions.alt_b as b',
                    'questions.alt_c as c',
                    'questions.alt_d as d',
                    'questions.response',
                    'questions.justification',
                    'questions.distractor_analysis',
                    'questions.reference'
                )->cursor();

            foreach ($questions as $value) {
                $body[] = [
                    'specialtyId' => (int) $value->id_specialty,
                    'questionId' => (int) $value->id_question,
                    'theme' => $value->theme,
                    'specialty' => $value->specialty,
                    'question' => $value->question,
                    'image' => self::getQuestionImageUrl($value->questionImage),
                    'comment' => $value->comment,
                    'image_comment' => $value->image_comment,
                    'options' => [
                        [
                            'optionId' => 'a',
                            'option' => $value->a,
                        ],
                        [
                            'optionId' => 'b',
                            'option' => $value->b,
                        ],
                        [
                            'optionId' => 'c',
                            'option' => $value->c,
                        ],
                        [
                            'optionId' => 'd',
                            'option' => $value->d,
                        ],
                    ],
                    'data' => $value->response,
                    'justification' => $value->justification,
                    'distractorAnalysis' => $value->distractor_analysis,
                    'reference' => $value->reference,
                ];
            }
            if (empty($body)) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }

            return CustomResponse::responseBody($body, Response::HTTP_OK);
        } catch (\Throwable $e) {
            report('Error en questions: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    public function getQuestionImageUrl(mixed $imagePath)
    {
        if (Str::of($imagePath)->startsWith('https')) {
            return $imagePath;
        }

        if (is_null($imagePath)) {
            return null;
        }

        return env('APP_IMAGE_STORAGE').Str::trim($imagePath);
    }

    public function encryptAnswer(string $answer): string
    {
        $key = hash('sha256', env('ENCRYPTION_KEY'), true);
        $iv = random_bytes(16);

        $encrypted = openssl_encrypt($answer, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv.$encrypted);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quiz/by-year",
     *     summary="Obtener preguntas por tipo de examen y año",
     *     tags={"Quiz"},
     *     description="Obtener preguntas por tipo de examen y año",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"year", "id_exam_type"},
     *
     *             @OA\Property(
     *                 property="year",
     *                 type="string",
     *                 description="Año de las preguntas",
     *                 example="2010"
     *             ),
     *             @OA\Property(
     *                 property="exam",
     *                 type="string",
     *                 description="Código del tipo de examen",
     *                 example="ENAM"
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=200,
     *     description="Preguntas obtenidas exitosamente",
     *
     *     @OA\JsonContent(
     *         type="array",
     *
     *         @OA\Items(
     *             type="object",
     *
     *             @OA\Property(property="specialtyId", type="integer", example=21),
     *             @OA\Property(property="questionId", type="integer", example=1066),
     *             @OA\Property(property="theme", type="string", example="PATOLOGÍA ESOFÁGICA ENAM"),
     *             @OA\Property(property="specialty", type="string", example="CARDIOLOGÍA"),
     *             @OA\Property(property="question", type="string", example="ENAM 2010 - ¿Cuál es el tumor benigno más frecuente en el esófago?"),
     *             @OA\Property(property="image", type="string", nullable=true, example=null),
     *             @OA\Property(property="comment", type="string", nullable=true, example=null),
     *             @OA\Property(property="image_comment", type="string", nullable=true, example=null),
     *             @OA\Property(
     *                 property="options",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="optionId", type="string", example="a"),
     *                     @OA\Property(property="option", type="string", example="Leioma")
     *                 )
     *             ),
     *             @OA\Property(property="data", type="string", example="eyJpdiI6Ikdmc3VjclcrTDNNR0JtK0EiLCJ2YWx1ZSI6IjlRPT0iLCJtYWMiOiIiLCJ0YWciOiJVcHJlQSs3VFEyY0g3UWpoUjVUMVZ3PT0ifQ==")
     *         ),
     *         example={
     *             {
     *                 "specialtyId": 21,
     *                 "questionId": 1066,
     *                 "theme": "PATOLOGÍA ESOFÁGICA ENAM",
     *                 "specialty": "CARDIOLOGÍA",
     *                 "question": "ENAM 2010 - ¿Cuál es el tumor benigno más frecuente en el esófago?",
     *                 "image": null,
     *                 "comment": null,
     *                 "image_comment": null,
     *                 "options": {
     *                     {"optionId": "a", "option": "Leioma"},
     *                     {"optionId": "b", "option": "Mioma"},
     *                     {"optionId": "c", "option": "Fibromioma"},
     *                     {"optionId": "d", "option": "Liposarcoma"},
     *                     {"optionId": "e", "option": "Hemangioma"}
     *                 },
     *                 "data": "eyJpdiI6Ikdmc3VjclcrTDNNR0JtK0EiLCJ2YWx1ZSI6IjlRPT0iLCJtYWMiOiIiLCJ0YWciOiJVcHJlQSs3VFEyY0g3UWpoUjVUMVZ3PT0ifQ=="
     *             }
     *         }
     *     )
     * ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="No se encontraron preguntas",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No se encontraron registros"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token no válido"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente"),
     *         )
     *     )
     * )
     */
    public function examTypeYear(ExamTypeRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $questionsQuery = Question::join('themes', 'themes.id_theme', '=', 'questions.id_theme', 'inner', false)
                ->join('specialties', 'specialties.id_specialty', '=', 'themes.id_specialty', 'inner', false)
                ->where([
                    ['questions.status', '=', 1],
                    ['questions.year', '=', $request->year],
                    ['questions.id_exam_type', '=', $request->exam],
                ])->inRandomOrder()
                ->select(
                    'specialties.id_specialty',
                    'specialties.specialty',
                    'questions.id_question',
                    'themes.theme',
                    'questions.question',
                    'questions.alt_a',
                    'questions.alt_b',
                    'questions.alt_c',
                    'questions.alt_d',
                    'questions.alt_e',
                    'questions.response',
                    'questions.comment',
                    'questions.image_comment',
                    'questions.image',
                    'questions.justification',
                    'questions.distractor_analysis',
                    'questions.reference'
                )
                ->cursor();

            if ($questionsQuery->isEmpty()) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }

            $questions = [];
            foreach ($questionsQuery as $question) {
                $questions[] = [
                    'specialtyId' => (int) $question->id_specialty,
                    'questionId' => $question->id_question,
                    'theme' => $question->theme,
                    'specialty' => $question->specialty,
                    'question' => $question->question,
                    'image' => self::getQuestionImageUrl($question->image),
                    'comment' => $question->comment,
                    'image_comment' => $question->image_comment,
                    'options' => [
                        [
                            'optionId' => 'a',
                            'option' => $question->alt_a,
                        ],
                        [
                            'optionId' => 'b',
                            'option' => $question->alt_b,
                        ],
                        [
                            'optionId' => 'c',
                            'option' => $question->alt_c,
                        ],
                        [
                            'optionId' => 'd',
                            'option' => $question->alt_d,
                        ],
                        [
                            'optionId' => 'e',
                            'option' => $question->alt_e,
                        ],
                    ],
                    'data' => $question->response,
                    'justification' => $question->justification,
                    'distractorAnalysis' => $question->distractor_analysis,
                    'reference' => $question->reference,
                ];
            }

            return CustomResponse::responseBody($questions, 200);
        } catch (\Throwable $th) {
            Log::info('Error en examTypeYear: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', 500, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quiz/question/theme",
     *     summary="Obtener preguntas por tema",
     *     tags={"Quiz"},
     *     description="Obtener todas las preguntas asociadas al tema",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"id"},
     *
     *             @OA\Property(
     *                 property="id",
     *                 type="string",
     *                 description="Identificador universal",
     *                 example="486e4576-7d33-11f0-b808-00090ffe0001"
     *             ),
     * ),
     * ),
     *
     * @OA\Response(
     *     response=200,
     *     description="Preguntas obtenidas exitosamente",
     *
     *     @OA\JsonContent(
     *         type="array",
     *
     *         @OA\Items(
     *             type="object",
     *
     *             @OA\Property(property="specialtyId", type="integer", example=21),
     *             @OA\Property(property="questionId", type="integer", example=1066),
     *             @OA\Property(property="theme", type="string", example="PATOLOGÍA ESOFÁGICA ENAM"),
     *             @OA\Property(property="specialty", type="string", example="CARDIOLOGÍA"),
     *             @OA\Property(property="question", type="string", example="ENAM 2010 - ¿Cuál es el tumor benigno más frecuente en el esófago?"),
     *             @OA\Property(property="image", type="string", nullable=true, example=null),
     *             @OA\Property(property="comment", type="string", nullable=true, example=null),
     *             @OA\Property(property="image_comment", type="string", nullable=true, example=null),
     *             @OA\Property(
     *                 property="options",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="optionId", type="string", example="a"),
     *                     @OA\Property(property="option", type="string", example="Leioma")
     *                 )
     *             ),
     *             @OA\Property(property="data", type="string", example="eyJpdiI6Ikdmc3VjclcrTDNNR0JtK0EiLCJ2YWx1ZSI6IjlRPT0iLCJtYWMiOiIiLCJ0YWciOiJVcHJlQSs3VFEyY0g3UWpoUjVUMVZ3PT0ifQ==")
     *         ),
     *         example={
     *             {
     *                 "specialtyId": 21,
     *                 "questionId": 1066,
     *                 "theme": "PATOLOGÍA ESOFÁGICA ENAM",
     *                 "specialty": "CARDIOLOGÍA",
     *                 "question": "ENAM 2010 - ¿Cuál es el tumor benigno más frecuente en el esófago?",
     *                 "image": null,
     *                 "comment": null,
     *                 "image_comment": null,
     *                 "options": {
     *                     {"optionId": "a", "option": "Leioma"},
     *                     {"optionId": "b", "option": "Mioma"},
     *                     {"optionId": "c", "option": "Fibromioma"},
     *                     {"optionId": "d", "option": "Liposarcoma"},
     *                     {"optionId": "e", "option": "Hemangioma"}
     *                 },
     *                 "data": "eyJpdiI6Ikdmc3VjclcrTDNNR0JtK0EiLCJ2YWx1ZSI6IjlRPT0iLCJtYWMiOiIiLCJ0YWciOiJVcHJlQSs3VFEyY0g3UWpoUjVUMVZ3PT0ifQ=="
     *             }
     *         }
     *     )
     * ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="No se encontraron preguntas",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No se encontraron registros"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token no válido"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente"),
     *         )
     *     )
     * )
     */
    public function questionsByTheme(QuestionByThemeRequest $request)
    {
        $language = $request->query('lang');
        try {
            $questionsQuery = Question::join('themes', 'questions.id_theme', '=', 'themes.id_theme', 'inner', false)
                ->join('specialties', 'themes.id_specialty', '=', 'specialties.id_specialty', 'inner', false)
                ->where('themes.uuid', $request->id)
                ->where('questions.status', 1)
                ->select(
                    'specialties.id_specialty',
                    'specialties.specialty',
                    'questions.id_question',
                    'themes.theme',
                    'questions.question',
                    'questions.image',
                    'questions.comment',
                    'questions.image_comment',
                    'questions.alt_a',
                    'questions.alt_b',
                    'questions.alt_c',
                    'questions.alt_d',
                    'questions.alt_e',
                    'questions.response',
                    'questions.justification',
                    'questions.distractor_analysis',
                    'questions.reference'
                )
                ->inRandomOrder()
                ->cursor();
            if ($questionsQuery->isEmpty()) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }
            $questions = [];
            foreach ($questionsQuery as $question) {
                $questions[] = [
                    'specialtyId' => (int) $question->id_specialty,
                    'questionId' => $question->id_question,
                    'theme' => $question->theme,
                    'specialty' => $question->specialty,
                    'question' => $question->question,
                    'image' => self::getQuestionImageUrl($question->image),
                    'comment' => $question->comment,
                    'image_comment' => $question->image_comment,
                    'options' => [
                        [
                            'optionId' => 'a',
                            'option' => $question->alt_a,
                        ],
                        [
                            'optionId' => 'b',
                            'option' => $question->alt_b,
                        ],
                        [
                            'optionId' => 'c',
                            'option' => $question->alt_c,
                        ],
                        [
                            'optionId' => 'd',
                            'option' => $question->alt_d,
                        ],
                        [
                            'optionId' => 'e',
                            'option' => $question->alt_e,
                        ],
                    ],
                    'data' => $question->response,
                    'justification' => $question->justification,
                    'distractorAnalysis' => $question->distractor_analysis,
                    'reference' => $question->reference,
                ];
            }

            return CustomResponse::responseBody($questions, 200);
        } catch (\Throwable $th) {
            Log::info('Error en questionsByTheme: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', 500, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quiz/report",
     *     tags={"Quiz"},
     *     summary="Enviar reporte del usuario",
     *     description="Permite a los clientes autenticados enviar reporte por pregunta erronea",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"questionId", "reason"},
     *
     *             @OA\Property(property="questionId", type="integer", example=1, description="Identificador de la pregunta"),
     *             @OA\Property(property="reason", type="string", example="La pregunta es incorrecta", description="Razón por el cual esta mal la pregunta")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Registro del reporte",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se guardo los datos correctamente")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno en el servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function report(ReportRequest $request)
    {
        $language = $request->query('lang');
        try {
            $user = auth('sanctum')->user();
            $report = new Report;
            $report->id_client = $user->id_client;
            $report->id_question = $request->questionId;
            $report->reason = $request->reason;
            $report->save();

            return CustomResponse::responseMessage('register', Response::HTTP_OK, $language);
        } catch (\Throwable $th) {
            Log::info('Error al enviar reporte: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quiz/history",
     *     tags={"Quiz"},
     *     summary="Registrar historia de preguntas",
     *     description="Permite registrar el historial de preguntas por tema del cliente",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * @OA\RequestBody(
     *     required=true,
     *
     *     @OA\JsonContent(
     *         type="array",
     *
     *         @OA\Items(
     *             required={"questionId", "ok", "error", "empty", "count"},
     *
     *             @OA\Property(property="questionId", type="string", example="312", description="Identificador de la pregunta"),
     *             @OA\Property(property="ok", type="integer", example=2, description="La cantidad de preguntas bien contestadas"),
     *             @OA\Property(property="error", type="integer", example=1, description="La cantidad de preguntas mal contestadas"),
     *             @OA\Property(property="empty", type="integer", example=0, description="La cantidad de preguntas en blanco"),
     *             @OA\Property(property="count", type="integer", example=3, description="La cantidad de preguntas totales generadas")
     *         ),
     *      ),
     * ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Registro del reporte",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se guardo los datos correctamente")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno en el servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function history(LanguageRequest $request)
    {
        $language = $request->query('lang');
        try {
            $user = auth('sanctum')->user();
            $payload = json_decode($request->getContent(), true);

            $questionIds = array_column($payload, 'questionId');

            $themes = Question::whereIn('id_question', $questionIds, 'and', false)
                ->pluck('id_theme', 'id_question');

            $grouped = [];

            foreach ($payload as $value) {

                $idTheme = $themes[$value['questionId']] ?? null;

                if (! $idTheme) {
                    continue;
                }

                if (! isset($grouped[$idTheme])) {
                    $grouped[$idTheme] = [
                        'id_client' => $user->id_client,
                        'id_theme' => $idTheme,
                        'ok' => 0,
                        'error' => 0,
                        'empty' => 0,
                        'count' => 0,
                    ];
                }
                $grouped[$idTheme]['ok'] += $value['ok'];
                $grouped[$idTheme]['error'] += $value['error'];
                $grouped[$idTheme]['empty'] += $value['empty'];
                $grouped[$idTheme]['count'] += $value['count'];
            }

            History::upsert(
                array_values($grouped),
                uniqueBy: ['id_client', 'id_theme'],
                update: ['ok', 'error', 'empty', 'count']
            );

            return CustomResponse::responseMessage('register', Response::HTTP_OK, $language);
        } catch (\Throwable $th) {
            Log::info('Error al enviar historia: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quiz/ranking",
     *     tags={"Quiz"},
     *     summary="Actualizar ranking de cliente",
     *     description="Permite actualizar el ranking actual del cliente",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"points"},
     *
     *             @OA\Property(property="points", type="integer", example=4, description="Puntos a sumar al ranking del cliente")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Actualización del ranking",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se actualizó el ranking correctamente")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno en el servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function ranking(RankingRequest $request)
    {
        $language = $request->query('lang');
        try {
            $user = auth('sanctum')->user();
            $user = Client::select('id_client', 'points')->find($user->id_client);
            $user->increment('points', $request->points);
            $user->save();

            return CustomResponse::responseMessage('updateRanking', Response::HTTP_OK, $language);
        } catch (\Throwable $th) {
            Log::info('Error al actualizar ranking: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quiz/ranking",
     *     tags={"Quiz"},
     *     summary="Listado de ranking de clientes",
     *     description="Devuelve un listado ordenado de clientes por sus puntos en el ranking, mostrando nombre, apellido y puntos",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Listado del ranking exitoso",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="name", type="string", example="Jaime", description="Nombre del cliente"),
     *                 @OA\Property(property="last_name", type="string", example="Pérez", description="Apellido del cliente"),
     *                 @OA\Property(property="points", type="integer", example=6, description="Puntos acumulados en el ranking"),
     *                 @OA\Property(property="university", type="string", example="UTP", description="Universidad del usuario")
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
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="No se encontraron registros",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No se encontraron registros")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Límite de peticiones excedido",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function userRanking(LanguageRequest $request)
    {
        $language = $request->query('lang');
        try {
            $userRanking = Client::orderBy('points', 'desc')->limit(env('LIMIT_USER_RANKING', 10))->get(['id_client AS profileId', 'name', 'last_name', 'points', 'university'])
                ->filter(function ($item) {
                    return $item->profileId = (int) $item->profileId;
                });
            if ($userRanking->isEmpty()) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }

            return CustomResponse::responseBody($userRanking, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error al mostrar ranking de un usuario: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quiz/history",
     *     tags={"Quiz"},
     *     summary="Listado de historia de clientes",
     *     description="Devuelve el listado de historia del cliente, mostrando themeId,name, ok,error,empty,count",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Pagina",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Limite",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="groupBy",
     *         in="query",
     *         description="Agrupar por",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Listado del ranking exitoso",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="themeId", type="integer", example=1, description="Identificador único de la tabla"),
     *                 @OA\Property(property="theme", type="string", example="ANATOMIA ENAM", description="Nombre del tema"),
     *                 @OA\Property(property="ok", type="integer", example=3, description="Cantidad de preguntas correctas"),
     *                 @OA\Property(property="error", type="integer", example=2, description="Cantidad de preguntas incorrectas"),
     *                 @OA\Property(property="empty", type="integer", example=2, description="Cantidad de preguntas en blanco"),
     *                 @OA\Property(property="count", type="integer", example=7, description="Cantidad de preguntas en total")
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
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="No se encontraron registros",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No se encontraron registros")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Límite de peticiones excedido",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function userHistory(HistoryRequest $request)
    {
        $language = $request->query('lang');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', env('LIMIT_USER_HISTORY', 10));
        $groupBy = $request->query('groupBy', 'theme');
        try {
            $user = auth('sanctum')->user();
            $query = History::query()
                ->where('history.id_client', $user->id_client);
            switch ($groupBy) {
                case 'specialty':
                    $query
                        ->join('themes', 'themes.id_theme', '=', 'history.id_theme')
                        ->join('specialties', 'specialties.id_specialty', '=', 'themes.id_specialty')
                        ->select(
                            'specialties.id_specialty as id',
                            'specialties.specialty as name',
                            DB::raw('SUM(history.ok) as ok'),
                            DB::raw('SUM(history.error) as error'),
                            DB::raw('SUM(history.empty) as empty'),
                            DB::raw('SUM(history.count) as count')
                        )
                        ->groupBy(
                            'specialties.id_specialty',
                            'specialties.specialty'
                        );

                    break;
                case 'area':

                    $query
                        ->join('themes', 'themes.id_theme', '=', 'history.id_theme')
                        ->join('specialties', 'specialties.id_specialty', '=', 'themes.id_specialty')
                        ->join('areas', 'areas.id_area', '=', 'specialties.id_area')
                        ->select(
                            'areas.id_area as id',
                            'areas.area as name',
                            DB::raw('SUM(history.ok) as ok'),
                            DB::raw('SUM(history.error) as error'),
                            DB::raw('SUM(history.empty) as empty'),
                            DB::raw('SUM(history.count) as count')
                        )
                        ->groupBy(
                            'areas.id_area',
                            'areas.area'
                        );

                    break;

                default:

                    $query
                        ->join('themes', 'themes.id_theme', '=', 'history.id_theme')
                        ->select(
                            'themes.id_theme as id',
                            'themes.theme as name',
                            DB::raw('SUM(history.ok) as ok'),
                            DB::raw('SUM(history.error) as error'),
                            DB::raw('SUM(history.empty) as empty'),
                            DB::raw('SUM(history.count) as count')
                        )
                        ->groupBy(
                            'themes.id_theme',
                            'themes.theme'
                        );

                    break;
            }
            $history = $query->paginate(
                $limit,
                ['*'],
                'page',
                $page
            );
            $currentPage = $history->currentPage();
            $data = collect($history->items())->map(function ($item) {
                $item->id = (int) $item->id;

                return $item;
            });

            if ($data->isEmpty()) {
                return CustomResponse::responseMessage(
                    'notFoundRegister',
                    Response::HTTP_BAD_REQUEST,
                    $language
                );
            }

            $body = [
                'history' => $data,
                'current_page' => $currentPage,
                'last_page' => $history->lastPage(),
            ];

            return CustomResponse::responseBody(
                $body,
                Response::HTTP_OK
            );
        } catch (\Throwable $th) {
            Log::info('Error el history de un usuario: '.$th->getMessage());

            return $th->getMessage();//CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quiz/exam",
     *     tags={"Quiz"},
     *     summary="Registrar examen realizado por el usuario",
     *     description="Permite guardar el resultado de un examen realizado por un cliente autenticado",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"exam_type", "title", "total_questions", "correct_answers", "incorrect_answers", "empty_answers", "score_percentage", "time_spent", "started_at", "completed_at", "exam_summary"},
     *
     *             @OA\Property(property="id_client", type="integer", example=1, description="Identificador del cliente. Se toma del usuario autenticado."),
     *             @OA\Property(property="exam_type", type="string", example="simulador", description="Tipo de examen"),
     *             @OA\Property(property="title", type="string", example="Examen de práctica", description="Título del examen"),
     *             @OA\Property(property="description", type="string", example="Examen realizado el día de hoy", description="Descripción del examen"),
     *             @OA\Property(property="total_questions", type="integer", example=30, description="Cantidad total de preguntas"),
     *             @OA\Property(property="score_percentage", type="number", format="float", example=73.3, description="Porcentaje de acierto"),
     *             @OA\Property(property="time_spent", type="integer", example=1800, description="Tiempo empleado en segundos"),
     *             @OA\Property(property="started_at", type="string", format="date-time", example="2026-08-05T10:15:00Z", description="Fecha y hora de inicio del examen"),
     *             @OA\Property(property="completed_at", type="string", format="date-time", example="2026-08-05T10:45:00Z", description="Fecha y hora de finalización del examen"),
     *             @OA\Property(
     *                 property="exam_summary",
     *                 type="array",
     *                 description="Resumen detallado de cada pregunta del examen con la respuesta correcta y la respuesta del usuario",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"question_id", "correct_answer", "response"},
     *
     *                     @OA\Property(property="question_id", type="integer", example=101, description="ID de la pregunta"),
     *                     @OA\Property(property="correct_answer", type="string", example="A", description="Respuesta correcta de la pregunta"),
     *                     @OA\Property(property="response", type="string", example="B", description="Respuesta seleccionada por el usuario")
     *                 ),
     *                 example={
     *                     {"question_id": 101, "correct_answer": "A", "response": "A"},
     *                     {"question_id": 102, "correct_answer": "B", "response": "C"},
     *                     {"question_id": 103, "correct_answer": "C", "response": ""},
     *                     {"question_id": 104, "correct_answer": "D", "response": "D"}
     *                 }
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Examen registrado correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se registró el examen correctamente")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Datos inválidos",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="exam_type", type="string", example="El campo exam_type es obligatorio"),
     *                 @OA\Property(property="total_questions", type="string", example="El campo total_questions es obligatorio"),
     *                 @OA\Property(property="score_percentage", type="string", example="El campo score_percentage debe ser numérico"),
     *                 @OA\Property(property="completed_at", type="string", example="El campo completed_at debe ser una fecha válida"),
     *                 @OA\Property(property="exam_summary", type="string", example="El campo exam_summary debe ser un array válido")
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
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function saveExamRegister(RegisterExamRequest $request)
    {
        $language = $request->query('lang', 'es');
        try {
            $exam = Exam::create([
                'id_client' => auth('sanctum')->user()->id_client,
                'exam_type' => $request->exam_type,
                'title' => $request->title,
                'total_questions' => $request->total_questions,
                'score_percentage' => null,
                'time_spent' => null,
                'exam_summary' => null,
                'started_at' => Carbon::parse($request->started_at)->setTimezone('America/Lima')->format('Y-m-d H:i:s'),
                'status' => 'in_progress',
            ]);
            $uuid = Exam::firstWhere('id_exam', '=', $exam->id_exam)->uuid;

            return CustomResponse::responseBody(['exam' => $uuid], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            Log::info('Error el guardado de examen de un usuario: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/quiz/exam/status",
     *     tags={"Quiz"},
     *     summary="Actualizar el estado y resultados de un examen",
     *     description="Permite actualizar el estado, porcentaje, tiempo, resumen y fecha de finalización de un examen existente utilizando su UUID",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"exam", "status"},
     *
     *             @OA\Property(
     *                 property="exam",
     *                 type="string",
     *                 example="550e8400-e29b-41d4-a716-446655440000",
     *                 description="UUID único del examen a actualizar"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"pending", "in_progress", "completed"},
     *                 example="completed",
     *                 description="Nuevo estado del examen"
     *             ),
     *             @OA\Property(
     *                 property="score_percentage",
     *                 type="number",
     *                 format="float",
     *                 example=73.3,
     *                 description="Porcentaje de acierto (opcional)"
     *             ),
     *             @OA\Property(
     *                 property="time_spent",
     *                 type="integer",
     *                 example=1800,
     *                 description="Tiempo empleado en segundos (opcional)"
     *             ),
     *             @OA\Property(
     *                 property="exam_summary",
     *                 type="array",
     *                 description="Resumen detallado de cada pregunta del examen (opcional)",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"question_id", "correct_answer", "response"},
     *
     *                     @OA\Property(property="question_id", type="integer", example=101, description="ID de la pregunta"),
     *                     @OA\Property(property="correct_answer", type="string", example="A", description="Respuesta correcta de la pregunta"),
     *                     @OA\Property(property="response", type="string", example="B", description="Respuesta seleccionada por el usuario")
     *                 ),
     *                 example={
     *                     {"question_id": 101, "correct_answer": "A", "response": "A"},
     *                     {"question_id": 102, "correct_answer": "B", "response": "C"},
     *                     {"question_id": 103, "correct_answer": "C", "response": ""},
     *                     {"question_id": 104, "correct_answer": "D", "response": "D"}
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="completed_at",
     *                 type="string",
     *                 format="date-time",
     *                 example="2026-08-05T10:45:00Z",
     *                 description="Fecha y hora de finalización del examen (opcional)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Estado del examen actualizado correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="updateExamStatus")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Datos inválidos o examen no encontrado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="status", type="string", example="El campo status es obligatorio"),
     *                 @OA\Property(property="exam_uuid", type="string", example="El campo exam_uuid es obligatorio"),
     *                 @OA\Property(property="score_percentage", type="string", example="El campo score_percentage debe ser numérico"),
     *                 @OA\Property(property="completed_at", type="string", example="El campo completed_at debe ser una fecha válida"),
     *                 @OA\Property(property="exam_summary", type="string", example="El campo exam_summary debe ser un array válido")
     *             ),
     *             @OA\Property(property="message", type="string", example="notFoundRegister")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="serverError")
     *         )
     *     )
     * )
     */
    public function updateExamStatus(UpdateExamStatusRequest $request)
    {
        $language = $request->query('lang', 'es');
        try {
            $exam = Exam::where('uuid', '=', $request->exam, 'and')->first();
            if (! $exam) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }
            $exam->score_percentage = $request->score_percentage;
            $exam->time_spent = $request->time_spent;
            $exam->exam_summary = $request->exam_summary;
            $exam->completed_at = Carbon::parse($request->completed_at)->setTimezone('America/Lima')
                                  ->format('Y-m-d H:i:s');
            $exam->status = $request->status;
            $exam->save();

            return CustomResponse::responseMessage('updateExamStatus', Response::HTTP_OK, $language);
        } catch (\Throwable $th) {
            Log::info('Error al actualizar el estado del examen: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quiz/exam",
     *     tags={"Quiz"},
     *     summary="Obtener exámenes del usuario",
     *     description="Obtiene el historial de exámenes realizados por el usuario autenticado. Puede filtrar opcionalmente por tipo de examen.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\Parameter(
     *         name="exam_type",
     *         in="query",
     *         description="Filtrar por tipo de examen",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="simulador")
     *     ),
     *
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Límite de resultados",
     *         required=true,
     *
     *         @OA\Schema(type="string", example=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=true,
     *
     *         @OA\Schema(type="string", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Exámenes obtenidos correctamente",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="title", type="string", example="Examen de práctica"),
     *                     @OA\Property(property="total_questions", type="integer", example=30),
     *                     @OA\Property(property="score_percentage", type="number", format="float", example=73.3),
     *                     @OA\Property(property="time_spent", type="integer", example=1800),
     *                     @OA\Property(property="started_at", type="string", format="date-time", example="2026-08-05T10:15:00Z"),
     *                     @OA\Property(property="completed_at", type="string", format="date-time", example="2026-08-05T10:45:00Z"),
     *                     @OA\Property(property="status", type="string", example="completed")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Datos inválidos",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="exam_type", type="string", example="El campo exam_type no es válido")
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
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="serverError")
     *         )
     *     )
     * )
     */
    public function getUserExams(ExamUserRequest $request)
    {
        $language = $request->query('lang', 'es');
        try {
            $query = Exam::select(['uuid', 'title', 'total_questions', 'score_percentage', 'exam_summary', 'time_spent', 'started_at','recommendation' ,'completed_at', 'status'])
                ->where('id_client', auth('sanctum')->user()->id_client)
                ->orderBy('started_at', 'desc');

            if ($request->has('exam_type') && ! empty($request->exam_type)) {
                $query->where('exam_type', $request->exam_type);
            }

            $exams = $query->paginate($request->limit, ['*'], 'page', $request->page);

            return CustomResponse::responseBody(['data' => $exams->items(), 'total' => $exams->total(), 'page' => $exams->currentPage(), 'limit' => $exams->perPage(), 'total_pages' => $exams->lastPage()], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error al obtener el examen del usuario: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quiz/exam/download-summary",
     *     tags={"Quiz"},
     *     summary="Descargar resumen de exámenes",
     *     description="Genera y envía por correo electrónico el resumen de uno o más exámenes seleccionados por el usuario. El proceso es asíncrono y los resultados se envían al correo del usuario.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma para los mensajes de respuesta",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="es")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos de los exámenes a descargar",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"exams"},
     *
     *             @OA\Property(
     *                 property="exams",
     *                 type="array",
     *                 description="Array de UUIDs de los exámenes a descargar",
     *
     *                 @OA\Items(
     *                     type="string",
     *                     format="uuid",
     *                     example="123e4567-e89b-12d3-a456-426614174000"
     *                 ),
     *
     *                 @OA\Examples(
     *                     example="multiple_exams",
     *                     summary="Múltiples exámenes",
     *                     value={"123e4567-e89b-12d3-a456-426614174000", "123e4567-e89b-12d3-a456-426614174001"}
     *                 )
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Solicitud procesada correctamente. Los resúmenes serán enviados por correo electrónico.",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="downloadExamSummary"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="integer",
     *                 example=200
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Error en la solicitud - Datos inválidos o correo no proporcionado",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Error de validación",
     *
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(
     *                             property="exams",
     *                             type="array",
     *
     *                             @OA\Items(type="string", example="El campo exams es obligatorio")
     *                         ),
     *
     *                         @OA\Property(
     *                             property="exams.*",
     *                             type="array",
     *
     *                             @OA\Items(type="string", example="El campo exams.* debe ser un UUID válido")
     *                         ),
     *
     *                         @OA\Property(
     *                             property="lang",
     *                             type="array",
     *
     *                             @OA\Items(type="string", example="El campo lang debe ser un valor alfabético")
     *                         )
     *                     )
     *                 ),
     *
     *                 @OA\Schema(
     *                     type="object",
     *                     description="Correo no proporcionado",
     *
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="noEmail"
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="integer",
     *                         example=400
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado - Token no válido o no proporcionado",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token no proporcionado"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Errores de validación",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="exams",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="El campo exams debe ser un array")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="El campo email debe ser una dirección de correo válida")
     *                 )
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
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="serverError"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="integer",
     *                 example=500
     *             )
     *         )
     *     )
     * )
     */
    public function downloadExamSummary(DownloadExamRerquest $request)
    {
        $language = $request->query('lang', 'es');
        try {
            $query = Exam::select(['title', 'total_questions', 'score_percentage', 'exam_summary', 'time_spent', 'started_at', 'completed_at'])
                ->selectRaw("CASE 
                                WHEN status = 'completed' THEN 'Completado' 
                                WHEN status = 'abandoned' THEN 'Abandonado' 
                                ELSE status 
                            END as status")
                ->where('id_client', auth('sanctum')->user()->id_client)
                ->whereIn('uuid', $request->exams)
                ->orderBy('started_at', 'desc');

            if ($request->has('exam_type') && ! empty($request->exam_type)) {
                $query->where('exam_type', $request->exam_type);
            }

            $exams = $query->paginate($request->limit, ['*'], 'page', $request->page);
            $email = auth('sanctum')->user()->email;
            $name= auth('sanctum')->user()->name;

            GoogleQueue::sendQueue(['value' => ['type' => 3, 'email' => $email,'name'=> $name, 'exams' => $exams->items()]]);

            return CustomResponse::responseMessage('downloadExamSummary', Response::HTTP_OK, $language);
        } catch (\Throwable $th) {
            Log::info('Error al obtener el examen del usuario: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }
}
