<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'access' => \App\Http\Middleware\AccessControlMiddleware::class,
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'El archivo o tamaño total cargado supera el límite permitido de 100MB.');
        });

        $exceptions->render(function (\Throwable $e) {
            if (config('app.debug')) {
                return response(
                    "Exception: " . $e->getMessage() . "\n" .
                    "File: " . $e->getFile() . "\n" .
                    "Line: " . $e->getLine() . "\n" .
                    "Trace: " . $e->getTraceAsString(),
                    500,
                    ['Content-Type' => 'text/plain']
                );
            }
            return response(
                '<!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <title>Error del Servidor</title>
                    <style>
                        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                        .container { text-align: center; max-width: 500px; padding: 2rem; border: 1px solid #f97316; border-radius: 12px; background: #1e293b; }
                        h1 { color: #f97316; margin-top: 0; }
                        a { color: #38bdf8; text-decoration: none; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>🔄 Ups, algo salió mal</h1>
                        <p>Ha ocurrido un inconveniente al procesar tu solicitud. Por favor, intenta de nuevo más tarde o contacta al administrador.</p>
                        <p><a href="/">Volver al Inicio</a></p>
                    </div>
                </body>
                </html>',
                500,
                ['Content-Type' => 'text/html']
            );
        });
    })->create();
