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

// Todo agrupado bajo "/api/v1"
Route::prefix('v1')->group(function () {

    // =========================================================================
    // 1. RUTAS PÚBLICAS (¡SIN MIDDLEWARE!)
    // IMPORTANTE: El login y register TIENEN que estar aquí fuera para entrar.
    // =========================================================================
    
    Route::get('/server-time', function () {
        return response()->json(['server_time' => now()->toISOString()]);
    });

    // ¡HECHO! Login y Register son públicos.
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // Configuración inicial de Admins (Mantengo esto público como lo tenías)
    Route::post('/assign-admin/{uid}', [AdminUserController::class, 'assignAdminClaim']);
    Route::get('/verify-admin/{uid}', [AdminUserController::class, 'verifyAdminClaim']);
    Route::post('/create-first-admin', [AdminUserController::class, 'createFirstAdmin']);


    // =========================================================================
    // 2. RUTAS PROTEGIDAS (USUARIO NORMAL)
    // Solo se puede entrar aquí si envías el Token válido
    // =========================================================================
    // NOTA: 'firebase.auth' requiere que las credenciales del servidor Firebase sean correctas.
    Route::middleware(['firebase.auth', 'check.user'])->group(function () {
        
        // Auth Check
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Vuelos y Predicciones
        Route::post('/predict-delay', [DelayController::class, 'predict']);
        Route::get('/flight-live/{icao}', [FlightController::class, 'getFlightData']);
        Route::get('/flights/live', [FlightController::class, 'getAllFlights']);
        Route::get('/flights/area', [FlightController::class, 'getFlightsByArea']);
        Route::get('/flights/nearby', [FlightController::class, 'getNearbyFlights']);

        // OpenSky
        Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);
        Route::get('/flights/airport/{icao_code}', [OpenSkyController::class, 'getLiveFlightsToAirport']);

        // Aeropuertos y Aerolíneas
        Route::get('/airports', [AirportController::class, 'index']);
        Route::get('/airports/{icao_code}', [OpenSkyController::class, 'getAirportInfo']);
        Route::get('/airport-info/{icao_code}', [AirportController::class, 'show']);
        Route::get('/airlines', [AirlineController::class, 'index']);

        // Flight Views (Historial)
        Route::post('/flight/view', [FlightViewController::class, 'storeFlightView']);

        // Preferencias
        Route::get('/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/preferences', [UserPreferencesController::class, 'update']);
    });


    // =========================================================================
    // 3. ZONA ADMIN (LA TORRE DE CONTROL)
    // =========================================================================
    Route::middleware(['firebase.auth', 'check.admin'])->group(function () {

        // Debugging
        Route::get('/debug/trigger-error', function () {
            FailOnPurpose::dispatch();
            return "Trabajo fallido enviado a la cola.";
        });

        // Dashboard Stats
        Route::get('/admin/server-stats', [AdminDashboardController::class, 'serverStats']);
        Route::get('/admin/db-stats', [AdminDashboardController::class, 'getSystemStats']);
        Route::get('/admin/performance-stats', [AdminDashboardController::class, 'getRealPerformanceStats']);

        // Gestión de Usuarios
        Route::get('/admin/users', [AdminDashboardController::class, 'indexUsers']);
        Route::delete('/admin/users/{id}', [AdminDashboardController::class, 'deleteUser']);
        Route::patch('/admin/users/{id}/role', [AdminDashboardController::class, 'toggleRole']);
        
        // Logs y Actividad
        Route::get('/admin/ai-logs', [AdminDashboardController::class, 'getAiLogs']);
        Route::get('/admin/recent-users', [AdminDashboardController::class, 'getRecentUsers']);
        Route::get('/admin/opensky-ping', [AdminDashboardController::class, 'checkOpenSkyStatus']);

        // Métricas del Sistema (Hardware)
        Route::get('/admin/system/cpu-usage', [SystemMetricsController::class, 'cpuUsage']);
        Route::get('/admin/system/memory-usage', [SystemMetricsController::class, 'memoryUsage']);
        Route::post('/admin/system/{action}', [AdminDashboardController::class, 'runSystemAction']);
    });

});