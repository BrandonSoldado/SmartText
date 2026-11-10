<?php

use App\Models\Usuario;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Definimos que por defecto toda la app usará el guard 'api' (JWT) 
    | y el proveedor 'usuarios' (tu tabla personalizada).
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'usuarios'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Solo dejamos el guard 'api' configurado con JWT. Hemos eliminado 'web' 
    | porque este es un microservicio API puro.
    |
    */

    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'usuarios',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Le decimos a Laravel que busque los usuarios en tu modelo Usuario.
    |
    */

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => Usuario::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];