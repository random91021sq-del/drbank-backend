<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\LanguageRequest;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SpecialtyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/quiz/specialty",
     *     summary="Obtener listado de especialidades",
     *     tags={"Quiz"},
     *     description="Obtener listado de especialidades",
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
     *         name="area",
     *         in="query",
     *         description="ID de la área",
     *         required=true,
     *
     *         @OA\Schema(type="integer",example=1)
     *     ),
     *
     * @OA\Response(
     *     response=200,
     *     description="Listado de especialidades obtenido exitosamente",
     *
     *     @OA\JsonContent(
     *         type="array",
     *
     *         @OA\Items(
     *             type="object",
     *
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Medicina General")
     *         ),
     *         example={
     *             {"id": 1, "name": "Medicina General"},
     *             {"id": 2, "name": "Cardiología"},
     *             {"id": 3, "name": "Pediatría"}
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
     *         description="No se encontraron especialidades",
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
    public function specialty(LanguageRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $specialtiesTable = DB::table('specialties_by_exam')
                ->where(['id_area' => $request->area, 'id_exam_type' => $request->exam])
                ->select('id_specialty AS id', 'specialty AS name')->cursor()
                ->map(function ($item) {
                    return [
                        'id' => (int) $item->id,
                        'name' => $item->name,
                    ];
                });
            if ($specialtiesTable->isEmpty()) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }

            return CustomResponse::responseBody($specialtiesTable, Response::HTTP_OK);
        } catch (\Throwable $th) {
            report('Error en specialty: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }
}
