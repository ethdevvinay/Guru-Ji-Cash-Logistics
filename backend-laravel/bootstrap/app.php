<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRetailerMembership;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(prepend: [ForceJsonResponse::class]);
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsureAdminPermission::class,
            'ability' => CheckForAnyAbility::class,
            'password.changed' => EnsurePasswordChanged::class,
            'retailer.member' => EnsureRetailerMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->dontReport(ApiException::class);
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
