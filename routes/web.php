<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerServiceController;
use App\Http\Controllers\SshTerminalController;
use App\Http\Controllers\UserController;
use App\Models\Server;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard', [
        'servers' => Server::select('id', 'name', 'status', 'last_checked_at')->get(),
    ]);
})->middleware(['auth', 'verified']);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard', [
        'servers' => Server::select('id', 'name', 'status', 'last_checked_at')->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('users', UserController::class)->only([
        'index', 'store', 'update', 'destroy',
    ]);
    Route::resource('servers', ServerController::class)->only([
        'index', 'store', 'update', 'destroy',
    ]);
    Route::get('/servers/{server}/details', [ServerController::class, 'details'])->name('servers.details');
    Route::post('/credentials/reveal', [ServerController::class, 'revealCredential'])->name('credentials.reveal');

    Route::post('/servers/{server}/check', [ServerController::class, 'checkHealth'])->name('servers.check');
    Route::get('/servers/{server}/terminal', [SshTerminalController::class, 'show'])->name('servers.terminal');
    Route::post('/servers/{server}/connect', [SshTerminalController::class, 'connect'])->name('servers.connect');
    Route::post('/ssh/disconnect', [SshTerminalController::class, 'disconnect'])->name('ssh.disconnect');

    // Nested routes for Databases and Services
    Route::post('/servers/{server}/databases', [ServerDatabaseController::class, 'store'])->name('servers.databases.store');
    Route::put('/databases/{serverDatabase}', [ServerDatabaseController::class, 'update'])->name('servers.databases.update');
    Route::delete('/databases/{serverDatabase}', [ServerDatabaseController::class, 'destroy'])->name('servers.databases.destroy');

    Route::post('/servers/{server}/services', [ServerServiceController::class, 'store'])->name('servers.services.store');
    Route::put('/services/{serverService}', [ServerServiceController::class, 'update'])->name('servers.services.update');
    Route::delete('/services/{serverService}', [ServerServiceController::class, 'destroy'])->name('servers.services.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
