<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\SavedFlightController;
use App\Http\Controllers\FlightViewController;
use App\Http\Controllers\AirportController;

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

        //FlightView
        Route::post('/flight/view', [FlightViewController::class, 'storeFlightView']);

        // Preferencias
        Route::get('/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/preferences', [UserPreferencesController::class, 'update']);
    });


    Route::middleware(['firebase.auth', 'check.admin'])->group(function () {
        Route::get('/admin/metrics', [\App\Http\Controllers\Admin\AdminMetricsController::class, 'index']);
        Route::get('/users', [\App\Http\Controllers\Admin\AdminUserController::class, 'index']);
        Route::get('/admin/recent-users', [\App\Http\Controllers\Admin\AdminUserController::class, 'getRecentUsers']);

        Route::post('/admin/users/{uid}/assign-admin', [\App\Http\Controllers\Admin\AdminUserController::class, 'assignAdminRole']);

        Route::get('/admin/logs', [\App\Http\Controllers\Admin\AdminLogController::class, 'performanceStats']);

    });
});