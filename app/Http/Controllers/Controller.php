<?php

namespace App\Http\Controllers;
/**
 * @OA\Info(
 *     title="APIS LARAVEL-DRBANK",
 *     version="1.0",
 *     description="APIS LARAVEL-DRBANK"
 * )
 *
 * @OA\Server(url= L5_SWAGGER_CONST_HOST)
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     description="Bearer token to access these API endpoints",
 *     bearerFormat="JWT"
 * )
 */
abstract class Controller
{
    
}
