<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\FeedbackRequest;
use App\Models\Feedback;
use App\Services\GoogleQueue;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FeedbackController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/external/support",
     *     tags={"External"},
     *     summary="Enviar retroalimentación de mejora",
     *     description="Permite a los clientes autenticados enviar retroalimentación",
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
     *             required={"full_name", "email", "reason", "description"},
     *
     *             @OA\Property(property="full_name", type="string", example="Daniel Aldana", description="Nombre completo del cliente"),
     *             @OA\Property(property="email", type="string", format="email", example="aldanagerardo24@gmail.com", description="Email del cliente"),
     *             @OA\Property(property="reason", type="string", example="Otros", description="Razon de retroalimentacion"),
     *             @OA\Property(property="description", type="string", example="Prueba de envio de feedback", description="descripcion de la retroalimentacion"),
     *             @OA\Property(property="response", type="string", example="Y", description="Admin response (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Retroalimentación enviada exitosamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Retroalimentación enviada exitosamente")
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
    public function feedbackClient(FeedbackRequest $request)
    {
        $language = $request->query('lang');
        try {
            $profile = auth('sanctum')->user();
            $add = new Feedback;
            $add->id_client = $profile->id_client;
            $add->full_name = $request->full_name;
            $add->email = $request->email;
            $add->reason = $request->reason;
            $add->description = $request->description;
            $add->response = $request->response;
            $add->save();
            $body = [
                'type' => 4,
                'email' => $request->email,
                'name' => $request->full_name,
                'reason' => $request->reason,
                'description' => $request->description,
            ];
            GoogleQueue::sendQueue(['value' => $body]);

            return CustomResponse::responseMessage('supportSent', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al enviar retroalimentación: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }
}
