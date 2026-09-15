<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\SpecialtyRequest;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ThemeController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/v1/quiz/theme",
     *     summary="Obtener temas por especialidad",
     *     tags={"Quiz"},
     *     description="Retorna un grupo de temas basados en la especialidad proporcionada",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="specialty",
     *         in="query",
     *         description="Id de la especialidad",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     * @OA\Response(
     *     response=200,
     *     description="Listado de temas obtenido exitosamente",
     *     @OA\JsonContent(
     *         type="array",
     *         @OA\Items(
     *             type="object",
     *             @OA\Property(property="themeId", type="integer", example=1),
     *             @OA\Property(property="theme", type="string", example="ANATOMÍA ENAM"),
     *             @OA\Property(property="code", type="string", nullable=true, example=null),
     *             @OA\Property(property="description", type="string", nullable=true, example=null),
     *             @OA\Property(property="icon", type="string", nullable=true, example=null)
     *         ),
     *         example={
     *             {
     *                 "theme": "ANATOMÍA ENAM",
     *                 "id": "ab49bef0-7d30-11f0-88cc-0200fd8286ac"
     *             },
     *             {
     *                 "theme": "FARMACOLOGÍA",
     *                 "id": "ab49bff1-7d30-11f0-88cc-0200fd8286ac"
     *             }
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
     *         description="No se encontraron preguntas",
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
    public function listThemes(SpecialtyRequest $request)
    {
        $language = $request->query('lang');
        try {
            $themes = DB::table('themes_by_exam')
                     ->where(['id_specialty'=>$request->specialty,'id_exam_type'=>$request->exam])
                     ->get(['theme', 'uuid AS id']);
            if ($themes->isEmpty()) {
                return CustomResponse::responseMessage('notFoundRegister', Response::HTTP_BAD_REQUEST, $language);
            }
            return CustomResponse::responseBody($themes, 200);
        } catch (\Throwable $th) {
            Log::info('Error en listThemes: ' . $th->getMessage());
            return CustomResponse::responseMessage('serverError', 500, $language);
        }
    }
}
