<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\SavedFlightsController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(['role:user'])->group(function () {
        Route::post('/flight-data/store', [FlightDataController::class, 'store']);
        Route::get('/flight-data', [FlightDataController::class, 'getAllData']);
        Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);
        Route::get('/flights/nearby', [FlightDataController::class, 'getNearbyFlights']);
        Route::post('/predict-delay', [FlightDataController::class, 'predictDelay']);


        //Preferencias
        Route::get('/user/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/user/preferences', [UserPreferencesController::class, 'update']);

        // Vuelos guardados
        Route::get('/user/saved-flights', [SavedFlightsController::class, 'index']);
        Route::post('/user/saved-flights', [SavedFlightsController::class, 'store']);
        Route::delete('/user/saved-flights/{icao}', [SavedFlightsController::class, 'destroy']);

    });
});
