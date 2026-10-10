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

/*
* Controlador encargado de gestionar la autenticación de un usuario
*/
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

    /*
    * Función encargada de registrar un nuevo usuario
    */
    public function register(RegisterRequest $request)
    {
        $language = $request->query('lang');
        try {
            // Genera una cadena aleatoria de 30 caracteres
            $rawPass = Str::random(30);
            // Encripta la cadena aleatoria generada
            $hash = encrypt($rawPass);
            // Genera un código de activación aleatorio de 6 caracteres en minúscula
            $activate = Str::lower(Str::random(6));
            // Crea una instancia del modelo Client
            $client = new Client;
            // Elimina los espacios en blanco a inicio y fin del campo nombre
            $client->name = trim($request->name);
            // Elimina los espacios en blanco a inicio y fin del campo apellido
            $client->last_name = trim($request->last_name);
            // Elimina los espacios en blanco a inicio y fin del campo correo electrónico
            $client->email = trim($request->email);
            // Encripta la contraseña proporcionada por el usuario
            $client->password = Hash::make($request->password);
            // Asigna el nivel de usuario
            $client->level = 1;
            // Asigna el token de autenticación
            $client->token = $hash;
            // Asigna el código de activación
            $client->code_active = $activate;
            // Asigna la universidad
            $client->university = ! $request->university ? '' : $request->university;
            // Asigna el estado de inicio de sesión social
            $client->social_login = 0;
            // Asigna el estado de la cuenta como inactiva
            $client->status = 0;
            // Guarda el nuevo usuario en la base de datos
            $client->save();
            // Crea un arreglo con los datos del usuario y el código de activación
            $body = [
                'type' => 1,
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'code_activate' => $activate,
            ];
            // Envía los datos a la cola de Google para enviar el correo electrónico de activación
            GoogleQueue::sendQueue([
                'value' => $body,
            ]);

            // Retorna mensaje de que se envió el correo de verificación
            return CustomResponse::responseMessage('sentVerification', Response::HTTP_CREATED, $language);
        } catch (\Throwable $e) {
            Log::info('Error en register: '.$e->getMessage());

            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
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
    /*
    * Función encargada de autenticar a un usuario existente y activo
    */
    public function login(LoginRequest $request): JsonResponse
    {
        /*
        * Obtiene el idioma por query param
        */
        $language = $request->query('lang');
        try {
            /*
            * Inicializamos la variable de respuesta
            */
            $response = null;
            /*
            * Obtiene el cliente por su correo electrónico
            */
            $client = Client::select(['id_client', 'social_login'])->firstWhere('email', $request->email);
            /*
            * Verifica si se envia un token de firebase
            */
            if ($request->token_fcm) {
                /*
                * Crea una instancia del modelo ClientFirebases
                */
                $clientFirebase = new ClientFirebases;
                /*
                * Asigna el id del cliente al modelo ClientFirebases
                */
                $clientFirebase->id_client = $client->id_client;
                /*
                * Asigna el token de firebase al modelo ClientFirebases
                 */
                $clientFirebase->token_firebase = $request->token_fcm;
                /*
                * Guarda el registro del token de firebase en la base de datos
                 */
                $clientFirebase->save();
            }
            /*
            * Calcula la fecha y hora de expiración del token
            */
            $expired_at = now()->addHours((int) env('EXPIRED_TOKEN', 24));
            /*
            * Genera un token de acceso mediante Laravel Sanctum
            */
            $token = $client->createToken(env('APP_NAME'), ['*'], $expired_at)->plainTextToken;
            /*
            * Prepara el cuerpo de la respuesta
            */
            $body = [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'social_login' => (int) $client->social_login,
            ];
            /*
            * Genera una respuesta HTTP con las credenciales de autenticación
            */
            $response = CustomResponse::responseDefault($body, Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::info('Error en login: '.$e->getMessage());
            /*
            * Genera una respuesta HTTP con el mensaje de error
            */
            $response = CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }

        /*
        * Retorna la respuesta HTTP con las credenciales de autenticación o el mensaje de error
        */
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

    /*
    * Función encargada de renovar el token de acceso de estudiante
    */
    public function refresh(LanguageRequest $request): JsonResponse
    {
        /*
        * Obtiene el idioma enviado por query param
        */
        $language = $request->query('lang');
        try {
            /*
            * Obtiene el token de autenticación enviado en el encabezado de la solicitud
            */
            $token = $request->bearerToken();
            /*
            * Busca el token recibido en la tabla
            */
            $issuedTokens = PersonalAccessToken::findToken($token);
            /*
            * Verifica si el token no fue encontrado
            */
            if (! $issuedTokens) {
                // Si no existe devuelve un mensaje de token inválido
                return CustomResponse::responseMessage('invalidToken', Response::HTTP_BAD_REQUEST, $language);
            }
            /*
            * Busca el cliente asociado al token
            */
            $client = Client::select('id_client')->find($issuedTokens->tokenable_id);
            /*
            * Elimina el token anterior de la base de datos
            */
            $issuedTokens->delete();
            /*
            * Calcula la fecha y hora de expiración del nuevo token de acceso
            */
            $expired_at = now()->addHours((int) env('EXPIRED_TOKEN', 24));
            /*
            * Genera un nuevo token de acceso mediante Laravel Sanctum
            */
            $token = $client->createToken(env('APP_NAME'), ['*'], $expired_at)->plainTextToken;
            /*
            * Retorna los datos del nuevo token de acceso
            */
            $body = [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ];

            // Retorna el nuevo token de autenticación
            return CustomResponse::responseDefault($body, Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::info('Error al refrescar el token: '.$e->getMessage());

            // Retorna mensaje de error
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

    /*
    * Función encargada de activar la cuenta mediante un código de activación
    */
    public function activationClient(ActivationRequest $request): JsonResponse
    {
        // Obtiene el idioma enviado por query param
        $language = $request->query('lang');
        try {
            /*
            * Inicializa la variable de respuesta
            */
            $response = null;
            /*
            * Busca el cliente por el código de activación y correo electrónico proporcionados en la solicitud
            */
            $client = Client::select(['id_client', 'status'])->firstWhere(['code_active' => Str::lower($request->code), 'email' => $request->email]);
            /*
            * Asigna estado activo al cliente
            */
            $client->status = 1;
            /*
            * Guarda los cambios en la base de datos, activando la cuenta del cliente
            */
            $client->save();
            /*
            * Asigna el mensaje de respuesta indicando que la cuenta fue activada exitosamente
            */
            $response = CustomResponse::responseMessage('userActivated', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al activar la cuenta: '.$e->getMessage());
            // Asigna el mensaje de respuesta indicando un error interno del servidor
            $response = CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }

        // Retorna la respuesta con el mensaje de activación o el mensaje de error
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

    /*
    * Función encargada de validar el token de recuperación de contraseña de un usuario
    */
    public function recoveryValidation(LanguageRequest $request)
    {
        // Obtiene el idioma enviado por query param
        $language = $request->query('lang', 'es');
        // Obtiene el token enviado en el encabezado de la solicitud
        $token = $request->header('token');
        // Inicializa la variable de respuesta
        $response = null;
        // Verifica si el token no fue proporcionado
        if (! $token) {
            // Si no existe devuelve un mensaje de token no proporcionado
            $response = CustomResponse::responseMessage('notToken', 401, $language);
            // Verifica si el token es demasiado largo
        } elseif (Str::length($token) > env('SIZE_TOKEN_JWT')) {
            // Si es demasiado largo devuelve un mensaje de token inválido
            $response = CustomResponse::responseMessage('largeToken', 401, $language);
        } else {
            // Divide el token en partes utilizando el punto como delimitador
            $tokenParts = explode('.', $token);
            // Verifica si el token tiene la estructura correcta (tres partes)
            if (count($tokenParts) !== 3) {
                // Si no tiene la estructura correcta devuelve un mensaje de token inválido
                $response = CustomResponse::responseMessage('invalidTokenStructure', 401, $language);
            } else {
                // Decodifica el JWT y verifica su validez
                $response = $this->decodeToken($token, $language);
            }
        }

        // Retorna la respuesta con el mensaje de validación del token
        return $response;
    }

    /*
    * Función encargada de decodificar y validar un token JWT de recuperación de contraseña
    */
    public function decodeToken(mixed $token, string $language)
    {
        try {
            // Inicializa la variable de respuesta
            $response = null;
            // Decodifica el token JWT utilizando la clave de la aplicación y el algoritmo HS256
            $tokenData = JWT::decode($token, new Key(env('APP_KEY'), 'HS256'));
            // Obtiene la información almacenada en el campo enc del contenido del JWT
            $tokenDecode = json_decode(gzuncompress(base64_decode($tokenData->enc)));

            // Verifica que la información decodificada contenga el identificador del usuario
            if (! isset($tokenDecode->id_client)) {
                // Asigna un mensaje de respuesta indicando que el token es inválido
                $response = CustomResponse::responseMessage('invalidToken', 401, $language);
                // Verifica si el token ha sido utilizado previamente
            } elseif (! Cache::has('token_'.$tokenDecode->id_client)) {
                // Asigna un mensaje de respuesta indicando que el token ya ha sido utilizado
                $response = CustomResponse::responseMessage('tokenUsed', 401, $language);
                // Verifica la fecha de expiración del token
            } elseif (! isset($tokenDecode->exp) || now()->gt($tokenDecode->exp)) {
                // Asigna un mensaje de respuesta indicando que el token ha expirado
                $response = CustomResponse::responseMessage('expiredToken', 401, $language);
            } else {
                // Asigna un mensaje de respuesta indicando que el token es válido
                $response = CustomResponse::responseMessage('tokenValid', 200, $language);
            }
        } catch (\Exception $e) {
            Log::info('Error al decodificar el token: '.$e->getMessage());
            $response = CustomResponse::responseMessage('invalidToken', 401, $language);
        }

        // Retorna la respuesta con el mensaje de validación del token
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
    /*
    * Función encargada de realizar el proceso de recuperación de contraseña de un usuario
    */
    public function recoverPassword(RecoveryRequest $request)
    {
        //Obtiene el idioma enviado por query param
        $language = $request->query('lang');
        try {
            //Obtiene al usuario mediante su correo electronico
            $client = Client::select(['id_client as profileId', 'name', 'last_name'])->firstWhere('email', $request->email);
            //Prepara la información que se incluira dentro del token de recuperación
            $payload = ['id_client' => $client->profileId, 'exp' => now()->addHours(1)];
            //Convierte y comprime la información del usuario antes de incorporarla en el JWT
            $compressed = base64_encode(gzcompress(json_encode($payload), 9));
            //Genera un JWT firmado
            $token = JWT::encode(['enc' => $compressed], env('APP_KEY'), 'HS256');
            //Se almacena el token JWT en cache
            Cache::put('token_'.$client->profileId, $token);
            //Prepara el cuerpo que se enviara a la cola de mensajeria
            $body = [
                'type' => 2,
                'token' => $token,
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $request->email,
            ];
            //Se envia la informacion a Google Pub Sub
            GoogleQueue::sendQueue(([
                'value' => $body,
            ]));
            // Devuelve la respuesta de envió de correo de recuperacion
            return CustomResponse::responseMessage('recoverySent', Response::HTTP_OK, $language);
        } catch (\Throwable $e) {
            Log::info('Error al recuperar la contraseña: '.$e->getMessage());
            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
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

    /*
    * Función encargada de generar la nueva contraseña para el usuario
    */
    public function generateNewPassword(GenerateNewPasswordRequest $request)
    {
        // Obtiene el idioma por query param
        $language = $request->query('lang');
        try {
            //Obtiene el token por la cabecera y lo decodifica utilizando la llave de la aplicacion y el algoritmo HS256
            $tokenData = JWT::decode($request->header('token'), new Key(env('APP_KEY'), 'HS256'));
            //Recupera la información del estudiante almacenada dentro del campo enc del JWT
            $tokenDecode = json_decode(gzuncompress(base64_decode($tokenData->enc)));
            //Verifica si existe un token en cache asociado al identificador del usuario
            if (! Cache::has('token_'.$tokenDecode->id_client)) {
                //Retorna mensaje de token usado
                return CustomResponse::responseMessage('tokenUsed', 401, $language);
            }
            //Busca al usuario mediante su identificador obtenido del token
            $client = Client::select(['id_client', 'password', 'social_login'])->find($tokenDecode->id_client);
            //Remueve todos los tokens de autorizacion asociados al usuario
            PersonalAccessToken::where('tokenable_id', $client->id_client)->delete();
            //Se le asigma la nueva contraseña y se hashea
            $client->password = Hash::make($request->newPassword);
            //Verifica si el usuario tiene activado el indicador de inicio de sesion por red social
            if ($client->social_login == 1) {
                //Se le asigna inicio de sesion tradicional
                $client->social_login = 0;
            }
            //Se guardan los cambios en la base de datos
            $client->save();
            //Se elimina el cache del proceso de recuperacion del usuario
            Cache::delete('token_'.$client->id_client);
            //Retorna mensaje de contraseña actualizada correctamente
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

    /*
    * Función encargada de reenviar un nuevo codigo de activacion al usuario
    */
    public function resendActivation(ActivationAgainRequest $request): JsonResponse
    {
        //Obtiene el idioma por query param
        $language = $request->query('lang');
        try {
            //Se genera un código de activación de 6 caracteres aleatorios
            $activate = Str::lower(Str::random(6));
            //Se busca al usuario por su email
            $client = Client::select(['id_client', 'name', 'last_name', 'email', 'code_active'])->firstWhere('email', $request->email);
            //Se asigna el nuevo código de activación
            $client->code_active = $activate;
            //Se guarda en la base de datos
            $client->save();
            //Se arma la estructura de la información que se enviara a la cola de mensajeria
            $body = [
                'type' => 1,
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'code_activate' => $activate,
            ];
            //Se publica el mensaje a Pub Sub
            GoogleQueue::sendQueue([
                'value' => $body,
            ]);
            // Retorna respuesta de envio de verificación
            return CustomResponse::responseMessage('sentVerification', Response::HTTP_CREATED, $language);
        } catch (\Throwable $e) {
            Log::info('Error al reenviar el código de activación: '.$e->getMessage());
            return CustomResponse::responseMessage('serverError', Response::HTTP_INTERNAL_SERVER_ERROR, $language);
        }
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
    /*
    * Función encargada de obtener la información del usuario autenticado
    */
    public function clientProfile(LanguageRequest $request): JsonResponse
    {
        //Obtiene el idioma por query param
        $language = $request->query('lang');
        try {
            //Obtiene la información del usuario autenticado
            $client = auth('sanctum')->user();
            //Prepara la estructura de respuesta con los datos personales del usuario
            $data = [
                'name' => $client->name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'university' => $client->university,
            ];
            //Retorna la información del usuario
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
    /*
    * Función encargada del cierre de sesión del usuario
    */
    public function logout(LogoutRequest $request): JsonResponse
    {
        //Obtiene el idioma por query param
        $language = $request->query('lang');
        try {
            //Busca y elimina los tokens de autenticación del usuario
            PersonalAccessToken::where('tokenable_id', $request->tokenable_id)->delete();
            //Verifica si se envio token Firebase
            if ($request->token_fcm) {
                //Busca y elimina el registro del token firebase
                ClientFirebases::where('token_firebase', '=', $request->token_fcm, 'and')->delete();
            }
            //Retorna respuesta de cerrado de sesión exitoso
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

    /*
    * Función encargada de eliminar la cuenta del usuario autenticado
    */
    public function profileDelete(LanguageRequest $request): JsonResponse
    {
        //Obtiene el idioma por query param
        $language = $request->query('lang');
        try {
            //Obtiene los datos del usuario autenticado
            $profile = auth('sanctum')->user();
            //Obtiene el token enviado por la cabecera
            $token = $request->bearerToken();
            //Busca el token recibido por la cabecera
            $issuedTokens = PersonalAccessToken::findToken($token);
            //Verifica si el token ha sido encontrado
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
