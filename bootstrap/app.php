<?php

use App\Http\Controllers\InternalSshController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InternalSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('internal-secret')->group(function () {
                Route::post('/internal/ssh/validate-token', [InternalSshController::class, 'validateToken']);
                Route::post('/internal/ssh/credentials', [InternalSshController::class, 'credentials']);
                Route::post('/internal/ssh/mark-active', [InternalSshController::class, 'markActive']);
                Route::post('/internal/ssh/mark-closed', [InternalSshController::class, 'markClosed']);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'internal-secret' => InternalSecret::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
