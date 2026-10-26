<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlightDataController;
use App\Http\Controllers\OpenSkyController;
use App\Http\Controllers\UserPreferencesController;
use App\Http\Controllers\SavedFlightController;
use App\Http\Controllers\FlightViewController;
use App\Http\Controllers\DelayController;
use App\Http\Controllers\AirlineController;
use App\Http\Controllers\AirportStatsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\SystemMetricsController;
use App\Jobs\FailOnPurpose;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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

    // Login / Register (Firebase ID Token → Sanctum)
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register']);


    // HexDB Proxy (Directo sin caché)
    Route::prefix('hexdb')->group(function () {

        // 1. Datos técnicos reales del avión
        Route::get('/aircraft/{hex}', function ($hex) {
            $hex = strtolower(trim($hex));

            try {
                $response = Http::timeout(4)->get("https://hexdb.io/api/v1/aircraft/{$hex}");
                if ($response->successful() && !empty($response->json())) {
                    return response()->json($response->json(), 200);
                }
            } catch (\Throwable $e) {}

            return response()->json([
                'ICAO' => strtoupper($hex),
                'Registration' => 'N/A',
                'RegisteredOwners' => 'Commercial / Private',
                'Manufacturer' => 'Airliner',
                'Model' => '',
                'Type' => ''
            ], 200);
        });

        // 2. Ruta real por callsign
        Route::get('/route/{callsign}', function ($callsign) {
            $callsign = strtoupper(trim($callsign));

            try {
                $response = Http::timeout(4)->get("https://hexdb.io/callsign-route-iata", [
                    'callsign' => $callsign
                ]);
                $route = trim($response->body());
                if ($response->successful() && !empty($route) && str_contains($route, '-') && !str_contains($route, 'Unknown')) {
                    return response($route, 200)->header('Content-Type', 'text/plain');
                }
            } catch (\Throwable $e) {}

            return response('', 204)->header('Content-Type', 'text/plain');
        });

        // 3. Coordenadas reales de aeropuerto
        Route::get('/airport/{iata}', function ($iata) {
            $iata = strtoupper(trim($iata));

            try {
                $response = Http::timeout(4)->get("https://hexdb.io/api/v1/airport/iata/{$iata}");
                if ($response->successful()) {
                    return response($response->body(), 200)
                        ->header('Content-Type', 'application/json');
                }
            } catch (\Throwable $e) {}

            return response()->json(['error' => 'Airport not found'], 404);
        });

        // 4. Miniatura de imagen (Redirige directamente al origen sin descargar el archivo en tu servidor)
        Route::get('/image/{hex}', function ($hex) {
            $hex = strtolower(trim($hex));
            return redirect("https://hexdb.io/hex-image-thumb?hex={$hex}");
        });
    });
    // =========================================================================
    // 2. RUTAS PROTEGIDAS (USUARIO LOGUEADO)
    // =========================================================================
    Route::middleware([
        'auth:sanctum',
        \App\Http\Middleware\LogPerformance::class,
        \App\Http\Middleware\ApiMetrics::class,
        \App\Http\Middleware\LogResponseTime::class,
    ])->group(function () {

        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post('/predict-delay', [DelayController::class, 'predict']);
        Route::get('/flight-live/{icao}', [FlightController::class, 'getFlightData']);
        Route::get('/flights/live', [FlightController::class, 'getAllFlights']);
        Route::get('/flights/area', [FlightController::class, 'getFlightsByArea']);
        Route::get('/flights/nearby', [FlightController::class, 'getNearbyFlights']);

        Route::get('/opensky/states', [OpenSkyController::class, 'getStatesAll']);
        Route::get('/flights/airport/{icao_code}', [OpenSkyController::class, 'getLiveFlightsToAirport']);

        Route::get('/airports', [AirportStatsController::class, 'index']);
        Route::get('/airports/{icao_code}', [OpenSkyController::class, 'getAirportInfo']);
        Route::get('/airport-info/{icao_code}', [AirportStatsController::class, 'show']);

        Route::post('/flight/view', [FlightController::class, 'storeFlightView']);
        Route::get('/preferences', [UserPreferencesController::class, 'index']);
        Route::post('/preferences', [UserPreferencesController::class, 'update']);
    });

    // =========================================================================
    // 3. ZONA ADMIN
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