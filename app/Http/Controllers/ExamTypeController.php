<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\LanguageRequest;
use App\Models\ExamType;
use Symfony\Component\HttpFoundation\Response;

class ExamTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/quiz/exam-type",
     *     tags={"Quiz"},
     *     summary="Obtener tipos de exámenes disponibles",
     *     description="Retorna una lista de todos los tipos de exámenes activos",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     * @OA\Response(
     *     response=200,
     *     description="Lista de tipos de exámenes",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             @OA\Property(property="exam", type="string", example="ENAM")
     *         ),
     *         example={
     *             {"exam": "ENAM"}
     *         }
     *     )
     * ),
     *     @OA\Response(
     *         response=400,
     *         description="No se encontraron tipos de exámenes",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No encontraron registros")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token no válido"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Se superó el limite de peticiones",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Se superó el limite de peticiones")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Ocurrio un error, intentelo nuevamente")
     *         )
     *     )
     * )
     */
    public function ExamType(LanguageRequest $request)
    {
        try {
            $language = $request->query('lang');
            $examType = ExamType::all(['exam_type AS exam']);
            if ($examType->isEmpty()) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }
            return CustomResponse::responseBody($examType, Response::HTTP_OK);
        } catch (\Throwable $th) {
            report('Error en ExamTypeController: ' . $th->getMessage());
            return CustomResponse::responseMessage('serverError', 500, $language);
        }
    }
}
