<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AreaRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AreaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/quiz/area",
     *     summary="Obtener listado de áreas",
     *     tags={"Quiz"},
     *     description="Obtener listado de áreas",
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
     *     description="Listado de áreas obtenido exitosamente",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             type="object",
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
     *         response=400,
     *         description="No se encontraron áreas",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No se encontraron registros")
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
    public function areas(AreaRequest $request)
    {
        try {
            Log::info('request enviado: ' . json_encode($request->all()));
            $areas = DB::table('areas_by_exam')
                    ->where('id_exam_type', $request->exam)
                     ->get(['id_area as id','area as name']);
            return CustomResponse::responseBody($areas, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error en areas: ' . $th->getMessage());
            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $request->query('lang'));
        }
    }
}
