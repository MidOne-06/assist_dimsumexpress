<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // El 419 "Page Expired" crudo de Laravel confunde a colaboradores que
        // dejan la pantalla de login abierta mucho tiempo o vuelven atrás en
        // el navegador después de un intento anterior. En vez de esa página
        // en inglés sin contexto, se reintenta el mismo formulario con un
        // aviso claro en español.
        //
        // OJO: Handler::mapException() convierte TokenMismatchException en un
        // HttpException(419) genérico ANTES de llegar a los render()
        // registrados aquí -- por eso se intercepta por código de estado y
        // no por el tipo de excepción original.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            return back()
                ->withInput($request->except('password'))
                ->withErrors(['email' => 'Tu sesión expiró por inactividad. Vuelve a intentarlo.']);
        });
    })->create();
