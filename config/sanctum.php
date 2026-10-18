<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    'guard' => ['web'],

    // Cada sesión móvil recibe una fecha de vencimiento explícita al crearse.
    // Mantener esta opción en null evita invalidar prematuramente un token cuya
    // duración haya sido ajustada por seguridad en el futuro.
    'expiration' => null,

    // El prefijo no es secreto; hace que un token expuesto pueda detectarse
    // por las herramientas de análisis de secretos sin revelar su contenido.
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'dsex_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
