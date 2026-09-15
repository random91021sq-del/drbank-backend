<?php

namespace App\Http\Middleware;

use App\Custom\CustomResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class ValidateToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = $request->query('lang');
        $token = $request->bearerToken();
        $response = null;

        if (!$token) {
            $response = CustomResponse::responseMessage('notToken', 401, $language);
        } elseif (Str::length($token) > env('SIZE_TOKEN')) {
            $response = CustomResponse::responseMessage('largeToken', 401, $language);
        } else {
            $accessToken = PersonalAccessToken::findToken($token);

            if (!$accessToken || !$accessToken->tokenable_id) {
                $response = CustomResponse::responseMessage('invalidToken', 401, $language);
            } elseif ($accessToken->expires_at && now()->gt($accessToken->expires_at)) {
                $response = CustomResponse::responseMessage('expiredToken', 401, $language);
            }
        }

        return $response ?: $next($request);
    }
}
