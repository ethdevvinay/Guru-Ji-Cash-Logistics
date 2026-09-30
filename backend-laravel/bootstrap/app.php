<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        // Redirect unauthenticated users to the login page
        // Prevents "Route [login] not defined" error since our route is named 'admin.login'
        $middleware->redirectGuestsTo('/login');

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'device.bound' => \App\Http\Middleware\EnsureDeviceBound::class,
            'idempotent' => \App\Http\Middleware\EnforceIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
