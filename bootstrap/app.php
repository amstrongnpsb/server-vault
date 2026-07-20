<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('internal-secret')->group(function () {
                Route::post('/internal/ssh/validate-token', [\App\Http\Controllers\InternalSshController::class, 'validateToken']);
                Route::post('/internal/ssh/credentials', [\App\Http\Controllers\InternalSshController::class, 'credentials']);
                Route::post('/internal/ssh/mark-active', [\App\Http\Controllers\InternalSshController::class, 'markActive']);
                Route::post('/internal/ssh/mark-closed', [\App\Http\Controllers\InternalSshController::class, 'markClosed']);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'internal-secret' => \App\Http\Middleware\InternalSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
