<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\SavedFlightController;
use App\Http\Controllers\FlightViewController;
use App\Http\Controllers\AirportController;
use App\Http\Controllers\DelayController;
use App\Http\Controllers\AirlineController;
use App\Http\Controllers\AirportStatsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\SystemMetricsController;
use App\Jobs\FailOnPurpose;

/*
|--------------------------------------------------------------------------
| API Routes - VORKUTA
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // =========================================================================
    // 1. RUTAS PÚBLICAS (SIN AUTH)
    // =========================================================================

    Route::get('/server-time', fn () =>
        response()->json(['server_time' => now()->toISOString()])
    );

    // 🔓 Login / Register (Firebase ID Token → Sanctum)
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // =========================================================================
    // 2. RUTAS PROTEGIDAS (USUARIO LOGUEADO)
    // 🔐 SOLO Sanctum
    // =========================================================================
    Route::middleware([
        'auth:sanctum',
        'check.user',

        // logging (ya con usuario autenticado)
        \App\Http\Middleware\LogPerformance::class,
        \App\Http\Middleware\ApiMetrics::class,
        \App\Http\Middleware\LogResponseTime::class,
    ])->group(function () {

        // Auth
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Vuelos
        Route::post('/predict-delay', [DelayController::class, 'predict']);
        Route::get('/flight-live/{icao}', [FlightController::class, 'getFlightData']);
        Route::get('/flights/live', [FlightController::class, 'getAllFlights']);
        Route::get('/flights/area', [FlightController::class, 'getFlightsByArea']);
        Route::get('/flights/nearby', [FlightController::class, 'getNearbyFlights']);

        // OpenSky
        Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);
        Route::get('/flights/airport/{icao_code}', [OpenSkyController::class, 'getLiveFlightsToAirport']);

        // Aeropuertos / Aerolíneas
        Route::get('/airports', [AirportController::class, 'index']);
        Route::get('/airports/{icao_code}', [OpenSkyController::class, 'getAirportInfo']);
        Route::get('/airport-info/{icao_code}', [AirportController::class, 'show']);
        Route::get('/airlines', [AirlineController::class, 'index']);

        // Historial / Preferencias
        Route::post('/flight/view', [FlightViewController::class, 'storeFlightView']);
        Route::get('/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/preferences', [UserPreferencesController::class, 'update']);
    });

    // =========================================================================
    // 3. ZONA ADMIN
    // 🔐 Sanctum + check.admin
    // =========================================================================
    Route::middleware([
        'auth:sanctum',
        'check.admin',
    ])->group(function () {

        Route::get('/admin/server-stats', [AdminDashboardController::class, 'serverStats']);
        Route::get('/admin/db-stats', [AdminDashboardController::class, 'getSystemStats']);
        Route::get('/admin/performance-stats', [AdminDashboardController::class, 'getRealPerformanceStats']);

        Route::get('/admin/users', [AdminDashboardController::class, 'indexUsers']);
        Route::delete('/admin/users/{id}', [AdminDashboardController::class, 'deleteUser']);
        Route::patch('/admin/users/{id}/role', [AdminDashboardController::class, 'toggleRole']);

        Route::get('/admin/ai-logs', [AdminDashboardController::class, 'getAiLogs']);
        Route::get('/admin/recent-users', [AdminDashboardController::class, 'getRecentUsers']);
        Route::get('/admin/opensky-ping', [AdminDashboardController::class, 'checkOpenSkyStatus']);

        Route::get('/admin/system/cpu-usage', [SystemMetricsController::class, 'cpuUsage']);
        Route::get('/admin/system/memory-usage', [SystemMetricsController::class, 'memoryUsage']);
        Route::post('/admin/system/{action}', [AdminDashboardController::class, 'runSystemAction']);
    });
});
