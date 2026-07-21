<?php

/**
 * Laravel Application Bootstrap - API_C2D
 *
 * Configuración central de la aplicación Laravel.
 *
 * Componentes configurados:
 *   - Routing: Rutas web (login, dashboard, etc.)
 *   - Middleware:
 *       * TrustProxies: Permite que Laravel detecte HTTPS detrás de Nginx
 *       * CheckChat2DeskToken: Middleware personalizado para tokens C2D
 *   - Excepciones: Manejo centralizado de errores
 *
 * Nota sobre TrustProxies:
 *   Nginx proxea HTTPS → HTTP internamente.
 *   Sin TrustProxies, Laravel cree que es HTTP y genera URLs incorrectas.
 *   Con trustProxies(at: '*'), Laravel lee X-Forwarded-Proto correctamente.
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Confía en todos los proxies (Nginx está en el mismo servidor)
        // Permite que Laravel detecte HTTPS correctamente
        $middleware->trustProxies(at: '*');
        
        // Middleware personalizado para el dominio Chat2Desk
        $middleware->web(append: [
            \App\Http\Middleware\CheckChat2DeskToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
