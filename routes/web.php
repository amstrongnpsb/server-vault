<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified']);

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('users', App\Http\Controllers\UserController::class);
    Route::resource('servers', App\Http\Controllers\ServerController::class)->only([
        'index', 'store', 'update', 'destroy'
    ]);

    // Nested routes for Databases and Services
    Route::post('/servers/{server}/databases', [App\Http\Controllers\ServerDatabaseController::class, 'store'])->name('servers.databases.store');
    Route::put('/databases/{serverDatabase}', [App\Http\Controllers\ServerDatabaseController::class, 'update'])->name('servers.databases.update');
    Route::delete('/databases/{serverDatabase}', [App\Http\Controllers\ServerDatabaseController::class, 'destroy'])->name('servers.databases.destroy');

    Route::post('/servers/{server}/services', [App\Http\Controllers\ServerServiceController::class, 'store'])->name('servers.services.store');
    Route::put('/services/{serverService}', [App\Http\Controllers\ServerServiceController::class, 'update'])->name('servers.services.update');
    Route::delete('/services/{serverService}', [App\Http\Controllers\ServerServiceController::class, 'destroy'])->name('servers.services.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
