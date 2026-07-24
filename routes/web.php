<?php

use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerServiceController;
use App\Http\Controllers\RdpController;
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
    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:manage roles');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:manage roles');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:manage roles');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:manage roles');

    // Users
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:manage users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('permission:manage users');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:manage users');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:manage users');

    // Servers
    Route::get('/servers', [ServerController::class, 'index'])->name('servers.index')->middleware('permission:view servers');
    Route::post('/servers', [ServerController::class, 'store'])->name('servers.store')->middleware('permission:create servers');
    Route::put('/servers/{server}', [ServerController::class, 'update'])->name('servers.update')->middleware('permission:edit servers');
    Route::delete('/servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy')->middleware('permission:delete servers');
    Route::get('/servers/{server}/details', [ServerController::class, 'details'])->name('servers.details')->middleware('permission:view servers');
    Route::post('/credentials/reveal', [ServerController::class, 'revealCredential'])->name('credentials.reveal')->middleware('permission:edit servers');

    Route::post('/servers/{server}/check', [ServerController::class, 'checkHealth'])->name('servers.check')->middleware('permission:check server health');
    Route::post('/servers/{server}/duplicate', [ServerController::class, 'duplicate'])->name('servers.duplicate')->middleware('permission:create servers');
    Route::get('/servers/{server}/terminal', [SshTerminalController::class, 'show'])->name('servers.terminal')->middleware('permission:connect servers');
    Route::post('/servers/{server}/connect', [SshTerminalController::class, 'connect'])->name('servers.connect')->middleware('permission:connect servers');
    Route::post('/ssh/disconnect', [SshTerminalController::class, 'disconnect'])->name('ssh.disconnect')->middleware('permission:connect servers');

    Route::get('/servers/{server}/rdp', [RdpController::class, 'show'])->name('servers.rdp')->middleware('permission:connect servers');
    Route::post('/servers/{server}/rdp-connect', [RdpController::class, 'connect'])->name('servers.rdp-connect')->middleware('permission:connect servers');

    // Databases
    Route::post('/servers/{server}/databases', [ServerDatabaseController::class, 'store'])->name('servers.databases.store')->middleware('permission:manage database servers');
    Route::put('/databases/{serverDatabase}', [ServerDatabaseController::class, 'update'])->name('servers.databases.update')->middleware('permission:manage database servers');
    Route::delete('/databases/{serverDatabase}', [ServerDatabaseController::class, 'destroy'])->name('servers.databases.destroy')->middleware('permission:manage database servers');

    // Database Browser
    Route::get('/databases/{serverDatabase}', [DatabaseController::class, 'show'])->name('databases.show')->middleware('permission:manage database servers');
    Route::post('/databases/{serverDatabase}/test', [DatabaseController::class, 'test'])->name('databases.test')->middleware('permission:manage database servers');
    Route::get('/databases/{serverDatabase}/schemas', [DatabaseController::class, 'schemas'])->name('databases.schemas')->middleware('permission:manage database servers');
    Route::get('/databases/{serverDatabase}/tables', [DatabaseController::class, 'tables'])->name('databases.tables')->middleware('permission:manage database servers');
    Route::get('/databases/{serverDatabase}/columns', [DatabaseController::class, 'columns'])->name('databases.columns')->middleware('permission:manage database servers');
    Route::get('/databases/{serverDatabase}/browse', [DatabaseController::class, 'browse'])->name('databases.browse')->middleware('permission:manage database servers');
    Route::post('/databases/{serverDatabase}/query', [DatabaseController::class, 'query'])->name('databases.query')->middleware('permission:manage database servers');

    // Services
    Route::post('/servers/{server}/services', [ServerServiceController::class, 'store'])->name('servers.services.store')->middleware('permission:manage server services');
    Route::put('/services/{serverService}', [ServerServiceController::class, 'update'])->name('servers.services.update')->middleware('permission:manage server services');
    Route::delete('/services/{serverService}', [ServerServiceController::class, 'destroy'])->name('servers.services.destroy')->middleware('permission:manage server services');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
