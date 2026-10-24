<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // detrás de un proxy HTTPS (tailscale serve, nginx) para que las URLs salgan con https
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // el archivo supera post_max_size de PHP: el POST llega vacío, volvemos al formulario con el motivo
        $exceptions->render(fn (PostTooLargeException $e) => redirect()->route('formulario')->withErrors([
            'archivo' => 'El archivo es más grande de lo que acepta PHP (post_max_size = ' . ini_get('post_max_size') . ').',
        ]));
    })->create();
