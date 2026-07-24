<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();
        $dbStatus = 'ok';
    } catch (Exception $e) {
        $dbStatus = 'error';
    }

    try {
        // Check Redis connection
        Redis::ping();
        $redisStatus = 'ok';
    } catch (Exception $e) {
        $redisStatus = 'error';
    }

    $status = ($dbStatus === 'ok' && $redisStatus === 'ok') ? 'healthy' : 'degraded';

    return response()->json([
        'status' => $status,
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => $dbStatus,
            'redis' => $redisStatus,
        ],
    ], $status === 'healthy' ? 200 : 503);
});
