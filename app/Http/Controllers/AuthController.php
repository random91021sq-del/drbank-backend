<?php

namespace App\Http\Controllers;

use App\Custom\CustomResponse;
use App\Http\Requests\ActivationAgainRequest;
use App\Http\Requests\ActivationRequest;
use App\Http\Requests\GenerateNewPasswordRequest;
use App\Http\Requests\LanguageRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileNotificationRequest;
use App\Http\Requests\ProfileUpdRequest;
use App\Http\Requests\RecoveryRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Client;
use App\Models\ClientFirebases;
use App\Models\Feedback;
use App\Models\SocialProfile;
use App\Services\GoogleQueue;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * @OA\Post (
     *     path="/api/v1/auth/register",
     *     tags={"Auth"},
     *     summary="Crear una nueva cuenta de usuario",
     *     description="Permite crear un nuevo usuario de forma manual y genera un código de activación",
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
     *         description="User credentials",
     *
     *         @OA\JsonContent(
     *             required={"name","last_name","email","password"},
     *
     *             @OA\Property(property="name", type="string", example="Daniel"),
     *             @OA\Property(property="last_name", type="string", example="Aldana"),
     *             @OA\Property(property="email", type="string", example="aldanagerardo24@gmail.com"),
     *             @OA\Property(property="password", type="string", example="Dani$243"),
     *             @OA\Property(property="university", type="string", example="UTP")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Creado con exito",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Revisa tu correo electronico para activar tu cuenta."),
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=400,
     *     description="Petición erronea",
     *
     *     @OA\JsonContent(
     *
     *         @OA\Property(
     *             property="errors",
     *             type="object",
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="El usuario ya existe"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="El campo debe tener un mínimo de 8 caracteres"
     *             )
     *         )
     *     )
     * ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function register(RegisterRequest $request)
    {
        $language = $request->query('lang');
        try {
            $rawPass = Str::random(30);
            $hash = encrypt($rawPass);
            $activate = Str::lower(Str::random(6));
            $client = new Client;
            $client->name = trim($request->name);
            $client->last_name = trim($request->last_name);
            $client->email = trim($request->email);
            $client->password = Hash::make($request->password);
            $client->level = 1;
            $client->token = $hash;
            $client->code_active = $activate;
            $client->university = ! $request->university ? '' : $request->university;
            $client->social_login = 0;
            $client->status = 0;
            $client->save();
            $body = [
                'type' => 1,
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'code_activate' => $activate,
            ];
            // Enviamos a cola en google pub/sub
            GoogleQueue::sendQueue([
                'value' => $body,
            ]);

            return CustomResponse::responseMessage('sentVerification', Response::HTTP_CREATED, $language);
            // return CustomResponse::sendEmail($language, $request->email, $client, 1);
        } catch (\Throwable $e) {
            Log::info('Error en register: '.$e->getMessage());

            return $e->getMessage();//CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Auth"},
     *     summary="Inicia sesión de usuario",
     *     description="Permite loguear a un usuario existente y activo",
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
     *             required={"email", "password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="aldanagerardo24@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="Dani$243"),
     *             @OA\Property(property="token_fcm", type="string", example="1")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string",example="1|6easJ1h5mg83OGRNbNzE4Kc2d0wijRKb3HkbeyOocbedf52e"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="social_login", type="integer", example=0)
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=400,
     *     description="Petición erronea",
     *
     *     @OA\JsonContent(
     *
     *         @OA\Property(
     *             property="errors",
     *             type="object",
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="El campo debe tener un mínimo de 8 caracteres"
     *             )
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
     *             @OA\Property(property="message", type="string", example="El usuario no esta activo"),
     *             @OA\Property(property="code", type="string", example="E0001")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $response = null;
            $client = Client::select(['id_client', 'social_login'])->firstWhere('email', $request->email);
            if ($request->token_fcm && ! $request->id) {
                $clientFirebase = new ClientFirebases;
                $clientFirebase->id_client = $client->id_client;
                $clientFirebase->token_firebase = $request->token_fcm;
                $clientFirebase->save();
            }
            $expired_at = now()->addHours((int) env('EXPIRED_TOKEN', 24));
            $token = $client->createToken(env('APP_NAME'), ['*'], $expired_at)->plainTextToken;

            $body = [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'social_login' => (int) $client->social_login,
            ];

            $response = CustomResponse::responseDefault($body, Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::info('Error en login: '.$e->getMessage());
            $response = CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }

        return $response;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     tags={"Auth"},
     *     summary="Refresca el token de acceso",
     *     description="Permite refrescar el token de acceso del usuario",
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
     *         description="Token refrescado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="access_token", type="string", example="1|6easJ1h5mg83OGRNbNzE4Kc2d0wijRKb3HkbeyOocbedf52e"),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Petición erronea",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token no proporcionado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function refresh(LanguageRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $token = $request->bearerToken();
            $issuedTokens = PersonalAccessToken::findToken($token);

            if (! $issuedTokens) {
                return CustomResponse::responseMessage('invalidToken', Response::HTTP_BAD_REQUEST, $language);
            }

            $client = Client::select('id_client')->find($issuedTokens->tokenable_id);
            $issuedTokens->delete();
            $expired_at = now()->addHours((int) env('EXPIRED_TOKEN', 24));
            $token = $client->createToken(env('APP_NAME'), ['*'], $expired_at)->plainTextToken;

            $body = [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ];

            return CustomResponse::responseDefault($body, Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::info('Error al refrescar el token: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/activation",
     *     tags={"Auth"},
     *     summary="Activa una cuenta de usuario",
     *     description="Permite activar la cuenta de usuario a través de un código de activación",
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
     *             required={"email", "code"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="aldanagerardo24@gmail.com"),
     *             @OA\Property(property="code", type="string", example="abc123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cuenta activada correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Tu cuenta fue activada exitosamente")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Petición erronea",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="El código no es válido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function activationClient(ActivationRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $response = null;
            $client = Client::select(['id_client', 'status'])->firstWhere(['code_active' => Str::lower($request->code), 'email' => $request->email]);
            $client->status = 1;
            $client->save();

            $response = CustomResponse::responseMessage('userActivated', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al activar la cuenta: '.$e->getMessage());
            $response = CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }

        return $response;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/recovery-validation",
     *     tags={"Auth"},
     *     summary="Valida el token de recuperacion de contraseña",
     *     description="Permite validar el token de recuperacion de contraseña de un usuario",
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma de respuesta",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="Token",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token valido",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Token valido")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Estructura de token invalida")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function recoveryValidation(LanguageRequest $request)
    {
        $language = $request->query('lang', 'es');
        $token = $request->header('token');
        $response = null;
        if (! $token) {
            $response = CustomResponse::responseMessage('notToken', 401, $language);
        } elseif (Str::length($token) > env('SIZE_TOKEN_JWT')) {
            $response = CustomResponse::responseMessage('largeToken', 401, $language);
        } else {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                $response = CustomResponse::responseMessage('invalidTokenStructure', 401, $language);
            } else {
                $response = $this->decodeToken($token, $language);
            }
        }

        return $response;
    }

    public function decodeToken(mixed $token, string $language)
    {
        try {
            $response = null;
            $tokenData = JWT::decode($token, new Key(env('APP_KEY'), 'HS256'));
            $tokenDecode = json_decode(gzuncompress(base64_decode($tokenData->enc)));

            if (! isset($tokenDecode->id_client)) {
                $response = CustomResponse::responseMessage('invalidToken', 401, $language);
            } elseif (! Cache::has('token_'.$tokenDecode->id_client)) {
                $response = CustomResponse::responseMessage('tokenUsed', 401, $language);
            } elseif (! isset($tokenDecode->exp) || now()->gt($tokenDecode->exp)) {
                $response = CustomResponse::responseMessage('expiredToken', 401, $language);
            } else {
                $response = CustomResponse::responseMessage('tokenValid', 200, $language);
            }
        } catch (\Exception $e) {
            Log::info('Error al decodificar el token: '.$e->getMessage());
            $response = CustomResponse::responseMessage('invalidToken', 401, $language);
        }

        return $response;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/recovery",
     *     tags={"Auth"},
     *     summary="Solicita recuperación de contraseña",
     *     description="Permite solicitar la recuperación de la contraseña de un usuario",
     *
     *     @OA\Parameter(
     *         name="lang",
     *         in="query",
     *         description="Idioma de respuesta",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="aldanagerardo24@gmail.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Correo de recuperación enviado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="La ayuda va en camino, te hemos enviado un correo electrónico.")
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=400,
     *     description="Petición erronea",
     *
     *     @OA\JsonContent(
     *
     *         @OA\Property(
     *             property="errors",
     *             type="object",
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="El usuario no existe"
     *             )
     *         )
     *     )
     * ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function recoverPassword(RecoveryRequest $request)
    {
        $language = $request->query('lang');
        try {
            $response = null;
            $client = Client::select(['id_client as profileId', 'name', 'last_name'])->firstWhere('email', $request->email);
            $payload = ['id_client' => $client->profileId, 'exp' => now()->addHours(1)];
            $compressed = base64_encode(gzcompress(json_encode($payload), 9));
            $token = JWT::encode(['enc' => $compressed], env('APP_KEY'), 'HS256');
            Cache::put('token_'.$client->profileId, $token);
            $body = [
                'type' => 2,
                'token' => $token,
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $request->email,
            ];
            GoogleQueue::sendQueue(([
                'value' => $body,
            ]));

            // $response = CustomResponse::sendEmail($language, $request->email, $client, 2);
            return CustomResponse::responseMessage('recoverySent', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al recuperar la contraseña: '.$e->getMessage());
            $response = CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }

        return $response;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/recovery-password",
     *     tags={"Auth"},
     *     summary="Cambia tu nueva contraseña",
     *     description="Permite cambiar la  contraseña del usuario si se olvidó",
     *
     *     @OA\Parameter(
     *         name="token",
     *         in="header",
     *         description="Token de cambio de contraseña",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
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
     *             required={"newPassword"},
     *
     *             @OA\Property(property="newPassword", type="string", example="Dani$243")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña actualizada correctamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se actualizo la contraseña correctamente")
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=400,
     *     description="Petición erronea",
     *
     *     @OA\JsonContent(
     *
     *         @OA\Property(
     *             property="errors",
     *             type="object",
     *             @OA\Property(
     *                 property="newPassword",
     *                 type="string",
     *                 example="El campo es requerido"
     *             )
     *         )
     *     )
     * ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se superó el límite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se superó el límite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrió un error, inténtelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function generateNewPassword(GenerateNewPasswordRequest $request)
    {
        $language = $request->query('lang');
        try {
            $tokenData = JWT::decode($request->header('token'), new Key(env('APP_KEY'), 'HS256'));
            $tokenDecode = json_decode(gzuncompress(base64_decode($tokenData->enc)));
            if (! Cache::has('token_'.$tokenDecode->id_client)) {
                return CustomResponse::responseMessage('tokenUsed', 401, $language);
            }
            $client = Client::select(['id_client', 'password', 'social_login'])->find($tokenDecode->id_client);
            PersonalAccessToken::where('tokenable_id', $client->id_client)->delete();
            $client->password = Hash::make($request->newPassword);
            if ($client->social_login == 1) {
                $client->social_login = 0;
            }
            $client->save();
            Cache::delete('token_'.$client->id_client);

            return CustomResponse::responseMessage('passwordMatch', Response::HTTP_OK, $language);
        } catch (\Throwable $th) {
            Log::info('Error al generar la nueva contraseña: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/resend-activation",
     *     tags={"Auth"},
     *     summary="Reenvía el código de activación",
     *     description="Permite reenviar el código de activación a un usuario",
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
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="aldanagerardo24@gmail.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Código de activación reenviado",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Revise su correo electrónico e ingrese el código enviado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Petición erronea",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="El usuario no existe")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function resendActivation(ActivationAgainRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $response = null;
            $activate = Str::lower(Str::random(6));
            $client = Client::select(['id_client', 'name', 'last_name', 'email', 'code_active'])->firstWhere('email', $request->email);
            Log::info("cliente" . json_encode($client));
            $client->code_active = $activate;
            $client->save();
            $body = [
                'type' => 1,
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'code_activate' => $activate,
            ];
            GoogleQueue::sendQueue([
                'value' => $body,
            ]);

            return CustomResponse::responseMessage('sentVerification', Response::HTTP_CREATED, $language);
            // $response = CustomResponse::sendEmail($language, $request->email, $client, 1);
        } catch (\Throwable $e) {
            Log::info('Error al reenviar el código de activación: '.$e->getMessage());
            $response = CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }

        return $response;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Auth"},
     *     summary="Obtiene el perfil del usuario",
     *     description="Permite obtener el perfil del usuario autenticado",
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
     *         description="Perfil del usuario",
     *
     *         @OA\JsonContent(
     *                 type="object",
     *
     *                 @OA\Property(property="name", type="string", example="Daniel"),
     *                 @OA\Property(property="last_name", type="string", example="Aldana"),
     *                 @OA\Property(property="email", type="string", format="email", example="aldanagerardo24@gmail.com"),
     *                 @OA\Property(property="phone", type="string", nullable=true, example=null),
     *                 @OA\Property(property="university", type="string", nullable=true, example="UTP"),
     *             )
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
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function clientProfile(LanguageRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $client = auth('sanctum')->user();
            $data = [
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'university' => $client->university,
            ];

            return CustomResponse::responseBody($data, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::info('Error al obtener el perfil del usuario: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Auth"},
     *     summary="Cierra la sesión del usuario",
     *     description="Permite cerrar la sesión del usuario autenticado",
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
     *             required={"tokenable_id"},
     *
     *             @OA\Property(property="token_fcm", type="string", description="Token de Firebase para eliminar", example="1")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Sesión cerrada",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Sesión cerrada")
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
     *         response=409,
     *         description="Se supero el limite de intentos",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Se supero el limite de intentos")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            PersonalAccessToken::where('tokenable_id', $request->tokenable_id)->delete();
            if ($request->token_fcm) {
                ClientFirebases::where('token_firebase', '=', $request->token_fcm, 'and')->delete();
            }

            return CustomResponse::responseMessage('closedSession', Response::HTTP_OK, $language);
        } catch (\Throwable $th) {
            Log::info('Error en logout: '.$th->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/auth/delete",
     *     tags={"Auth"},
     *     summary="Elimina el perfil del usuario",
     *     description="Permite eliminar el perfil del usuario autenticado",
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
     *         description="Cuenta eliminada exitosamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="El usuario fue eliminado correctamente"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Token inválido o no proporcionado",
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
     *         response=500,
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Ocurrió un error, intente nuevamente"
     *             )
     *         )
     *     )
     * )
     */
    public function profileDelete(LanguageRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $profile = auth('sanctum')->user();
            $token = $request->bearerToken();
            $issuedTokens = PersonalAccessToken::findToken($token);
            if (! $issuedTokens) {
                return CustomResponse::responseMessage('invalidToken', Response::HTTP_BAD_REQUEST, $language);
            }
            $issuedTokens->delete();
            ClientFirebases::where('id_client', '=', $profile->id_client, 'and')->exists() ? ClientFirebases::where('id_client', '=', $profile->id_client, 'and')->delete() : null;
            SocialProfile::where('id_client', '=', $profile->id_client, 'and')->exists() ? SocialProfile::where('id_client', '=', $profile->id_client, 'and')->delete() : null;
            Feedback::where('id_client', '=', $profile->id_client, 'and')->exists() ? Feedback::where('id_client', '=', $profile->id_client, 'and')->delete() : null;
            Client::find($profile->id_client, ['id_client'])->delete();

            return CustomResponse::responseMessage('userDeleted', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al eliminar la cuenta: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/profile/notifications",
     *     tags={"Profile"},
     *     summary="Actualiza las preferencias de notificaciones",
     *     description="Permite actualizar las preferencias de notificaciones del usuario autenticado",
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
     *             required={"notifications"},
     *
     *             @OA\Property(property="notifications", type="boolean", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Notificación actualizada exitosamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Datos actualizados correctamente"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Petición erronea",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="El campo es requerido"
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
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Ocurrió un error, intentelo mas tarde"
     *             )
     *         )
     *     )
     * )
     */
    public function profileNotification(ProfileNotificationRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $profile = auth('sanctum')->user();
            $client = Client::select(['id_client', 'notifications'])->find($profile->id_client);
            $client->notifications = $request->notifications;
            $client->save();

            return CustomResponse::responseMessage('updatedData', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al actualizar las preferencias de notificaciones: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/profile/update",
     *     tags={"Profile"},
     *     summary="Actualiza los datos del perfil",
     *     description="Permite actualizar los datos del perfil del usuario autenticado",
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
     *             required={"name", "last_name"},
     *
     *             @OA\Property(property="name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="phone", type="string", example="123456789"),
     *             @OA\Property(property="university", type="string", example="UTP")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Datos de perfil actualizados",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Datos actualizados correctamente"
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=400,
     *     description="Petición erronea",
     *
     *     @OA\JsonContent(
     *
     *         @OA\Property(
     *             property="errors",
     *             type="object",
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 example="El telefono debe de tener 9 digitos"
     *             ),
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
     *                 example="Token no proporcionado"
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
     *         description="Error interno del servidor",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Ocurrió un error, intentelo mas tarde"
     *             )
     *         )
     *     )
     * )
     */
    public function profileUpdate(ProfileUpdRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $profile = auth('sanctum')->user();
            $client = Client::select(['id_client', 'name', 'last_name', 'phone', 'university'])->find($profile->id_client);
            $client->name = $request->name;
            $client->last_name = $request->last_name;
            $client->phone = ! $request->phone || $request->phone == '' ? '' : $request->phone;
            if ($request->university) {
                $client->university = $request->university;
            }
            $client->save();

            return CustomResponse::responseMessage('updatedData', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al actualizar los datos del perfil: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/profile/change-password",
     *     tags={"Profile"},
     *     summary="Cambia la contraseña del usuario",
     *     description="Permite cambiar la contraseña del usuario autenticado",
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
     *             required={"password"},
     *
     *             @OA\Property(property="password", type="string", format="password", example="1234567890"),
     *             @OA\Property(property="current_password", type="string", format="password", example="123456789")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña actualizada exitosamente",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Se actualizo la contraseña correctamente"
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
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Token no válido"
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *     response=400,
     *     description="Petición erronea",
     *
     *     @OA\JsonContent(
     *
     *         @OA\Property(
     *             property="errors",
     *             type="object",
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="La contraseña debe tener al menos una mayuscula y una miniscula"
     *             ),
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 example="La contraseña debe tener al menos una mayuscula y una miniscula"
     *             )
     *         )
     *     )
     * ),
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
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Ocurrió un error, intentelo mas tarde"
     *             )
     *         )
     *     )
     * )
     */
    public function changePassword(PasswordRequest $request): JsonResponse
    {
        $language = $request->query('lang');
        try {
            $profile = auth('sanctum')->user();
            $update = Client::select(['id_client', 'password'])->find($profile->id_client);
            $update->password = Hash::make($request->password);
            $update->save();

            return CustomResponse::responseMessage('passwordMatch', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al cambiar la contraseña: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
    }
}
