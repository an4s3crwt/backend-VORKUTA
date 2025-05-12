<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\SavedFlightController;
use App\Http\Controllers\FlightViewController;
use App\Http\Controllers\AirportController;

use App\Http\Controllers\AirlineController;



Route::prefix('v1')->group(function () {
    // Proteger  el login con firebase.auth (pero sin el middleware de rol aún)
    Route::middleware(['firebase.auth'])->post('/login', [AuthController::class, 'login']);

    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/assign-admin/{uid}', [\App\Http\Controllers\Admin\AdminUserController::class, 'assignAdminClaim']);
    Route::get('/verify-admin/{uid}', [\App\Http\Controllers\Admin\AdminUserController::class, 'verifyAdminClaim']);
    Route::post('create-first-admin', [\App\Http\Controllers\Admin\AdminUserController::class, 'createFirstAdmin']);

    // Rutas protegidas con middleware Firebase
    Route::middleware(['firebase.auth', 'check.user'])->group(function () {
        // Información del usuario
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/airlines', [AirlineController::class, 'index']);
        Route::get('/airlines/{id}', [AirlineController::class, 'show']);

        Route::get('/airports', [AirportController::class, 'index']);
        Route::get('/airports/{iata_code}', [AirportController::class, 'show']);


        // Vuelos guardados
        Route::post('/saved-flights', [SavedFlightController::class, 'store']);
        Route::get('/saved-flights', action: [SavedFlightController::class, 'index']);
        // Gestión de vuelos
        Route::prefix('flights')->group(function () {
            Route::get('/', [FlightDataController::class, 'getAllData']);
            Route::post('/', [FlightDataController::class, 'store']);
            Route::get('/nearby', [FlightDataController::class, 'getNearbyFlights']);
            Route::post('/predict-delay', [FlightDataController::class, 'predictDelay']);

        });




        // OpenSky
        Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);



        //
        Route::get('/flights/airport/{icao_code}', [OpenSkyController::class, 'getLiveFlightsToAirport']);

        //Airports and Airlines
        Route::get('/airports', [AirportController::class, 'index']);
        Route::get('/airports/{icao_code}', [OpenSkyController::class, 'getAirportInfo']);
        Route::get('/airlines', [AirlineController::class, 'index']);



        //
        Route::get('/airport-info/{icao_code}', [AirportController::class, 'show']);

        //FlightView
        Route::post('/flight/view', [FlightViewController::class, 'storeFlightView']);

        // Preferencias
        Route::get('/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/preferences', [UserPreferencesController::class, 'update']);
    });


    Route::middleware(['firebase.auth', 'check.admin'])->group(function () {

        Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index']);
        Route::get('/admin/recent-users', [\App\Http\Controllers\Admin\AdminUserController::class, 'getRecentUsers']);
        Route::get('/admin/saved-flights', [SavedFlightController::class, 'indexAll']); // Todos los vuelos guardados
        Route::post('/admin/users/{uid}/assign-admin', [\App\Http\Controllers\Admin\AdminUserController::class, 'assignAdminRole']);
        Route::get('/admin/api-metrics', [\App\Http\Controllers\Admin\AdminLogController::class, 'getApiMetrics']);
        Route::get('/admin/logs', [\App\Http\Controllers\Admin\AdminLogController::class, 'performanceStats']);
        Route::get('/admin/system/cpu-usage', [\App\Http\Controllers\Admin\SystemMetricsController::class, 'cpuUsage']);
        Route::get('/admin/system/memory-usage', [\App\Http\Controllers\Admin\SystemMetricsController::class, 'memoryUsage']);
        Route::get('/admin/system/disk-usage', [\App\Http\Controllers\Admin\SystemMetricsController::class, 'diskUsage']);
    });


    Route::middleware(['firebase.auth', 'check.admin', 'api.metrics'])->group(function () {
        Route::get('/admin/metrics', [\App\Http\Controllers\Admin\AdminMetricsController::class, 'index']);


    });
});