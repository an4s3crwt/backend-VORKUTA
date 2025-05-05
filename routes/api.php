<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\SavedFlightsController;

Route::prefix('v1')->group(function () {
    // Solo login: el frontend maneja registro con Firebase
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);


    // Rutas protegidas con middleware Firebase
    Route::middleware(['firebase.auth','role:user'])->group(function () {
        // Información del usuario
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Gestión de vuelos
        Route::prefix('flights')->group(function () {
            Route::get('/', [FlightDataController::class, 'getAllData']);
            Route::post('/', [FlightDataController::class, 'store']);
            Route::get('/nearby', [FlightDataController::class, 'getNearbyFlights']);
            Route::post('/predict-delay', [FlightDataController::class, 'predictDelay']);

            // Vuelos guardados
            Route::get('/saved', [SavedFlightsController::class, 'index']);
            Route::post('/saved', [SavedFlightsController::class, 'store']);
            Route::delete('/saved/{icao}', [SavedFlightsController::class, 'destroy']);
        });

        // OpenSky
        Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);

        // Preferencias
        Route::get('/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/preferences', [UserPreferencesController::class, 'update']);
    });
});