<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Custom\CustomResponse;
use App\Http\Requests\SocialRequest;
use App\Models\Client;
use App\Models\ClientFirebases;
use App\Models\SocialProfile;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Google_Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/v1/login/social",
     *     summary="Inicia sesión con red social",
     *     description="Permite al cliente iniciar sesión con una red social",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"driver", "access_token"},
     *             @OA\Property(property="driver", type="string", enum={"facebook", "google"}, example="google"),
     *             @OA\Property(property="access_token", type="string", example="ya29.a0AfH6SMB...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Logueo con red social exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="1|6easJ1h5mg83OGRNbNzE4Kc2d0wijRKb3HkbeyOocbedf52e"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="social_login", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Se supero el limite de peticiones",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Se supero el limite de peticiones")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error Interno",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Ocurrio un error,intentelo nuevamente.")
     *         )
     *     )
     * )
     */
    public function socialLogin(SocialRequest $request)
    {
        try {
            $driver = strtolower($request->driver);
            $token = $request->access_token;
            if ($driver == 'google-web') {
                $userCol = $this->google($token, $driver);
            } else {
                if ($driver == "facebook" && count(explode('.', $token)) == 3) {
                    $this->facebookMovil($token);
                } else {
                    $providerUser = Socialite::driver($driver)->userFromToken($token);
                    Log::info("providerUser:", (array)$providerUser);
                }
                $userCol = $this->loginFacebookApple($providerUser, $driver);
            }
            Log::info('Usuario obtenido: ' . json_encode($userCol));
            if ($request->token_fcm) {
                ClientFirebases::create([
                    'id_client' => $userCol->id_client,
                    'token_firebase' => $request->token_fcm
                ]);
            }

            $token = $userCol->createToken(env('APP_NAME'))->plainTextToken;

            $body = [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'social_login' => (int)$userCol->social_login
            ];

            return CustomResponse::responseDefault($body, 200);
        } catch (\Throwable $e) {
            Log::info('Error social login: ' . $e->getMessage());
            return CustomResponse::tryError('Ocurrió un problema al conectarse con ' . $driver . ', inténtalo en otro momento.');
        }
    }
    public function google($token, $driver)
    {
        $client = new Google_Client([
            'client_id' => env('GOOGLE_KEY')
        ]);

        $providerUser = $client->verifyIdToken($token);
        if (!$providerUser) {
            return CustomResponse::tryError('Ocurrió un problema al conectarse con Google, inténtalo en otro momento.');
        }
        $social_profile = SocialProfile::where('social_id', $providerUser['sub'])->where('social_name', $driver)->first();
        if (!$social_profile) {
            $userCol = Client::where('email', $providerUser['email'])->first();
            if (!$userCol) {
                $userCol = Client::create([
                    'name' => $providerUser['name'],
                    'email' => $providerUser['email'],
                    'level' => 1,
                    'password' => Hash::make('helloadmin'),
                    'type' => 1,
                    'status' => 1,
                    'photo' => '',
                    'social_login' => 1
                ]);
            }

            SocialProfile::create([
                'id_client' => $userCol->id_client,
                'social_id' => $providerUser['sub'],
                'social_name' => $driver,
                'social_avatar' => '',
            ]);
        } else {
            $userCol = Client::where('id_client', $social_profile->id_user)->first();
        }
        return $userCol;
    }
    public function facebookMovil($token)
    {
        $jwksUri = 'https://www.facebook.com/.well-known/oauth/openid/certs/';
        $jwks    = Http::get($jwksUri)->json();
        [$headb64] = explode('.', $token, 2);
        $header = json_decode(base64_decode($headb64), true);
        $kid = $header['kid'] ?? null;
        $publicKey = $jwks[$kid];

        $payload = (array) JWT::decode($token, new Key($publicKey, 'RS256'));

        Log::info("Data obtenida:", (array)$payload);
        return new class($payload) {
            private array $data;
            public function __construct(array $data)
            {
                $this->data = $data;
            }
            public function getId()
            {
                return $this->data['sub'];
            }
            public function getName()
            {
                return $this->data['name'] ?? null;
            }
            public function getEmail()
            {
                return $this->data['email'] ?? null;
            }
            public function getAvatar()
            {
                return $this->data['picture'] ?? null;
            }
            public function getRaw()
            {
                return $this->data;
            }
        };
    }
    public function loginFacebookApple($providerUser, $driver)
    {
        $social_profile = SocialProfile::where('social_id', $providerUser->getId())->where('social_name', $driver)->first();
        if (!$social_profile) {
            $userCol = Client::where('email', $providerUser->getEmail())->first();
            if (!$userCol) {
                if (is_null($providerUser->getEmail())) {
                    return CustomResponse::tryError('No logramos obtener el email de tu cuenta Apple, intente ingresar con el formulario de registro');
                } elseif (!$providerUser->getName()) {
                    Log::info("Estoy entrando aqui del elseif:" . $providerUser->getName());
                    $name = explode('@', $providerUser->getEmail());
                    $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name[0]);
                } else {
                    Log::info("Estoy entrando aqui en el else: " . $providerUser->getName());
                    $name = $providerUser->getName();
                }
                $userCol = Client::create([
                    'name' => $name,
                    'email' => $providerUser->getEmail(),
                    'level' => 1,
                    'password' => Hash::make('helloadmin'),
                    'type' => 1,
                    'status' => 1,
                    'photo' => '',
                    'social_login' => 1
                ]);
                Log::info('Creando usuario social: ' . json_encode($userCol));
            }
            Log::info('Usuario obtenido: ' . json_encode($userCol));
            SocialProfile::create([
                'id_client' => $userCol->id_client,
                'social_id' => $providerUser->getId(),
                'social_name' => $driver,
                'social_avatar' => ''
            ]);
        } else {
            Log::info('Social profile obtenido: ' . json_encode($social_profile));
            $userCol = Client::where('id_client', $social_profile->id_user)->first();
        }
        return $userCol;
    }
}
