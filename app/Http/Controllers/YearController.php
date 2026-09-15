<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\LanguageRequest;
use App\Models\Year;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class YearController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/quiz/year",
     *     summary="Obtener listado de años disponibles para preguntas",
     *     tags={"Quiz"},
     *     description="Obtener listado de años disponibles para preguntas",
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
     * @OA\Response(
     *     response=200,
     *     description="Listado de años obtenido exitosamente",
     *
     *     @OA\JsonContent(
     *         type="array",
     *
     *         @OA\Items(
     *             type="object",
     *
     *             @OA\Property(property="year", type="string", example="2023")
     *         ),
     *         example={
     *             {"year": "2023"},
     *             {"year": "2022"},
     *             {"year": "2021"}
     *         }
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
     *         description="No se encontraron años disponibles",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="No se encontraron registros"),
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
    public function year(LanguageRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {

            $year = Year::select('year')
                ->orderByRaw("
                    CAST(SUBSTRING(year,1,4) AS UNSIGNED),
                    CASE
                        WHEN LOWER(year) LIKE '%extraordinario%' THEN 1
                        WHEN LOWER(year) LIKE '%ordinario%' THEN 2
                        ELSE 3
                    END,
                    CAST(
                        CASE
                            WHEN LOWER(year) REGEXP 'extraordinario [0-9]+'
                            THEN SUBSTRING_INDEX(year, ' ', -1)
                            ELSE 0
                        END
                    AS UNSIGNED)
                ")
                ->get();
            if ($year->isEmpty()) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }

            return CustomResponse::responseBody($year, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('Error en year: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }
}
