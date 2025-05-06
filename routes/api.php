<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\SavedFlightController;

Route::prefix('v1')->group(function () {
    // Proteger  el login con firebase.auth (pero sin el middleware de rol aún)
    Route::middleware(['firebase.auth'])->post('/login', [AuthController::class, 'login']);

    Route::post('/register', [AuthController::class, 'register']);


    // Rutas protegidas con middleware Firebase
    Route::middleware(['firebase.auth', 'role:user'])->group(function () {
        // Información del usuario
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);


        // Vuelos guardados
        Route::post('/saved-flights', [SavedFlightController::class, 'store']);
        // Gestión de vuelos
        Route::prefix('flights')->group(function () {
            Route::get('/', [FlightDataController::class, 'getAllData']);
            Route::post('/', [FlightDataController::class, 'store']);
            Route::get('/nearby', [FlightDataController::class, 'getNearbyFlights']);
            Route::post('/predict-delay', [FlightDataController::class, 'predictDelay']);

        });

        // OpenSky
        Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);

        // Preferencias
        Route::get('/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/preferences', [UserPreferencesController::class, 'update']);
    });
});