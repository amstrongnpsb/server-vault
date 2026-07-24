<?php

use App\Http\Controllers\InternalRdpController;
use App\Http\Controllers\InternalSshController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InternalSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

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

                Route::post('/internal/rdp/validate-token', [InternalRdpController::class, 'validateToken']);
                Route::post('/internal/rdp/credentials', [InternalRdpController::class, 'credentials']);
                Route::post('/internal/rdp/mark-active', [InternalRdpController::class, 'markActive']);
                Route::post('/internal/rdp/mark-closed', [InternalRdpController::class, 'markClosed']);
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
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
