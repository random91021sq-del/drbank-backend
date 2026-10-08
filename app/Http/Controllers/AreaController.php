<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\AreaRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/*
* Controlador encargado de gestionar las áreas disponibles para la generación de exámenes
*/
class AreaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/quiz/area",
     *     summary="Obtener listado de áreas",
     *     tags={"Quiz"},
     *     description="Obtener listado de áreas",
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
     *         name="exam",
     *         in="query",
     *         description="Id del examen",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *     response=200,
     *     description="Listado de áreas obtenido exitosamente",
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
     *         description="No se encontraron áreas",
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
    /*
    Función encargada de obtener las áreas disponibles según el tipo de examen
    */
    public function areas(AreaRequest $request)
    {
        try {
            /*
            * Obtiene las áreas disponibles pero que tengan preguntas disponibles en el banco de preguntas
            */
            $areas = DB::table('questions')
                ->join('themes', 'questions.id_theme', '=', 'themes.id_theme')
                ->join('specialties', 'themes.id_specialty', '=', 'specialties.id_specialty')
                ->join('areas', 'specialties.id_area', '=', 'areas.id_area')
                ->where('questions.id_exam_type', $request->exam)
                ->where('questions.status', 1)
                ->where('themes.status', 1)
                ->where('specialties.status', 1)
                ->where('areas.status', 1)
                ->when($request->filled('year'), function ($query) use ($request) {
                    $query->whereIn('questions.year', (array) $request->year);
                })
                ->select('areas.id_area as id', 'areas.area as name')
                ->distinct()
                ->orderBy('areas.area', 'asc')
                ->get();
            /*
            * Convierte cada palabra del nombre del área a mayúscula
            */
            $areas->transform(function ($area) {
                $area->name = Str::title($area->name);

                return $area;
            });

            /*
            * Retorna el listado de áreas disponibles
            */
            return CustomResponse::responseBody($areas, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error en areas: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $request->query('lang'));
        }
    }
}
