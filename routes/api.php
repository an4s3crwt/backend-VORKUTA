<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\AuthController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('flight-data/store', [FlightDataController::class, 'store']);

Route::get('/flight-data', [FlightDataController::class, 'getAllData']);

Route::middleware('api')->group(function () {
    Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);
});


Route::get('/flights/nearby', [FlightDataController::class, 'getNearbyFlights']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class,'login']);
Route::middleware('auth:api')->get('/user', [AuthController::class], 'me');
