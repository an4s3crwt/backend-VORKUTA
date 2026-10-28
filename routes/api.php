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


    // Rutas de HexDB optimizadas en routes/api.php

Route::prefix('hexdb')->group(function () {

    // 1. Datos del avión con caché de 7 días (evita volver a consultar HexDB si ya se buscó)
    Route::get('/aircraft/{hex}', function ($hex) {
        $hex = strtolower(trim($hex));

        return Cache::remember("aircraft_{$hex}", 60 * 24 * 7, function () use ($hex) {
            try {
                $response = Http::timeout(3)->get("https://hexdb.io/api/v1/aircraft/{$hex}");
                if ($response->successful() && !empty($response->json())) {
                    return $response->json();
                }
            } catch (\Throwable $e) {}

            return [
                'ICAO' => strtoupper($hex),
                'Registration' => 'N/A',
                'RegisteredOwners' => 'Commercial / Private',
                'Manufacturer' => 'Airliner',
                'Model' => '',
                'Type' => ''
            ];
        });
    });

    // 2. Ruta con caché de 24 horas
    Route::get('/route/{callsign}', function ($callsign) {
        $callsign = strtoupper(trim($callsign));

        return Cache::remember("route_{$callsign}", 60 * 24, function () use ($callsign) {
            try {
                $response = Http::timeout(3)->get("https://hexdb.io/callsign-route-iata", [
                    'callsign' => $callsign
                ]);
                $route = trim($response->body());
                if ($response->successful() && !empty($route) && str_contains($route, '-') && !str_contains($route, 'Unknown')) {
                    return $route;
                }
            } catch (\Throwable $e) {}

            return null;
        });
    });

    // 3. Aeropuerto con caché de 30 días
    Route::get('/airport/{iata}', function ($iata) {
        $iata = strtoupper(trim($iata));

        return Cache::remember("airport_{$iata}", 60 * 24 * 30, function () use ($iata) {
            try {
                $response = Http::timeout(3)->get("https://hexdb.io/api/v1/airport/iata/{$iata}");
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {}

            return ['error' => 'Airport not found'];
        });
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