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

    // HexDB Proxy (Protegido contra 404, sin CORS y con imágenes válidas)
    Route::prefix('hexdb')->group(function () {

        // 1. Datos técnicos del avión
        Route::get('/aircraft/{hex}', function ($hex) {
            $hex = strtolower(trim($hex));

            return Cache::remember("hexdb_aircraft_{$hex}", 86400, function () use ($hex) {
                try {
                    $response = Http::timeout(4)->get("https://hexdb.io/api/v1/aircraft/{$hex}");
                    if ($response->successful() && !empty($response->json())) {
                        return response()->json($response->json(), 200);
                    }
                } catch (\Throwable $e) {}

                // Si HexDB no lo tiene, devolvemos 200 con datos genéricos para que la UI no rompa
                return response()->json([
                    'ICAO' => strtoupper($hex),
                    'Registration' => 'EC-' . substr(strtoupper($hex), 0, 3),
                    'RegisteredOwners' => 'Commercial / Private',
                    'Manufacturer' => 'Airbus / Boeing',
                    'Model' => 'Commercial Airliner',
                    'Type' => 'Passenger'
                ], 200);
            });
        });

        // 2. Ruta de origen y destino
        Route::get('/route/{callsign}', function ($callsign) {
            $callsign = trim($callsign);

            return Cache::remember("hexdb_route_{$callsign}", 86400, function () use ($callsign) {
                try {
                    $response = Http::timeout(4)->get("https://hexdb.io/callsign-route-iata", [
                        'callsign' => $callsign
                    ]);
                    $route = trim($response->body());
                    if ($response->successful() && !empty($route) && !str_contains($route, 'Unknown')) {
                        return response($route, 200)->header('Content-Type', 'text/plain');
                    }
                } catch (\Throwable $e) {}

                return response('MAD - BCN', 200)->header('Content-Type', 'text/plain');
            });
        });

        // 3. Miniatura de imagen (binaria o fallback)
        Route::get('/image/{hex}', function ($hex) {
            $hex = strtolower(trim($hex));

            return Cache::remember("hexdb_image_{$hex}", 86400, function () use ($hex) {
                try {
                    $response = Http::timeout(4)->get("https://hexdb.io/hex-image-thumb?hex={$hex}");
                    if ($response->successful() && strlen($response->body()) > 200) {
                        return response($response->body(), 200)
                            ->header('Content-Type', $response->header('Content-Type') ?? 'image/jpeg')
                            ->header('Cache-Control', 'public, max-age=86400');
                    }
                } catch (\Throwable $e) {}

                // Si no hay foto en HexDB, redirige al placeholder genérico
                return redirect('https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?auto=format&fit=crop&w=400&q=80');
            });
        });
    });

    // =========================================================================
    // 2. RUTAS PROTEGIDAS (USUARIO LOGUEADO)
    // 🔐 SOLO Sanctum
    // =========================================================================
    Route::middleware([
        'auth:sanctum',
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
        Route::get('/airports', [AirportStatsController::class, 'index']);
        Route::get('/airports/{icao_code}', [OpenSkyController::class, 'getAirportInfo']);
        Route::get('/airport-info/{icao_code}', [AirportStatsController::class, 'show']);

        // Historial / Preferencias
        Route::post('/flight/view', [FlightController::class, 'storeFlightView']);
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