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
use App\Jobs\FailOnPurpose;


Route::prefix('v1')->group(function () {
    Route::get('/server-time', function () {
        return response()->json([
            'server_time' => now()->toISOString()
        ]);


    });
    // Proteger  el login con firebase.auth (pero sin el middleware de rol aún)
    Route::middleware(['firebase.auth'])->post('/login', [AuthController::class, 'login']);

    Route::post('/register', [AuthController::class, 'register']);

    Route::post('/assign-admin/{uid}', [\App\Http\Controllers\Admin\AdminUserController::class, 'assignAdminClaim']);
    Route::get('/verify-admin/{uid}', [\App\Http\Controllers\Admin\AdminUserController::class, 'verifyAdminClaim']);
    Route::post('create-first-admin', [\App\Http\Controllers\Admin\AdminUserController::class, 'createFirstAdmin']);

    // Rutas protegidas con middleware Firebase
    Route::middleware(['firebase.auth', 'check.user'])->group(function () {
        Route::post('/predict-delay', [DelayController::class, 'predict']);
        Route::get('/flight-live/{icao}', [FlightController::class, 'getFlightData']);
        //  NUEVA RUTA: Para el Dashboard (Lista completa)
        Route::get('/flights/live', [FlightController::class, 'getAllFlights']);
        Route::get('/flights/area', [FlightController::class, 'getFlightsByArea']);

        Route::get('/flights/nearby', [FlightController::class, 'getNearbyFlights']);
        // NUEVA RUTA: Estadísticas para el Dashboard del Aeropuerto

        // Información del usuario
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);







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


    // =========================================================================
    // --- ZONA ADMIN (LA TORRE DE CONTROL) ---
    // Aquí conectamos el Dashboard de React con tus tablas SQL
    // =========================================================================
    Route::middleware(['firebase.auth', 'check.admin'])->group(function () {



        Route::get('/debug/trigger-error', function () {
            FailOnPurpose::dispatch();
            return "Trabajo fallido enviado a la cola. Ahora ejecuta 'php artisan queue:work'";
        });

        // 1. ESTADÍSTICAS DE INFRAESTRUCTURA (Para las tarjetas de colores)
        // Conecta con 'flight_positions', 'failed_jobs', 'telescope_entries'
        Route::get('/admin/db-stats', [AdminDashboardController::class, 'getSystemStats']);

        // 2. GESTIÓN DE USUARIOS (Para la tabla del Dashboard)
        Route::get('/admin/users', [AdminDashboardController::class, 'indexUsers']);
        Route::delete('/admin/users/{id}', [AdminDashboardController::class, 'deleteUser']); // Borrar
        Route::patch('/admin/users/{id}/role', [AdminDashboardController::class, 'toggleRole']); // Cambiar Rol

        Route::get('/admin/ai-logs', [AdminDashboardController::class, 'getAiLogs']);
        Route::get('/admin/recent-users', [AdminDashboardController::class, 'getRecentUsers']);
        Route::get('/admin/opensky-ping', [AdminDashboardController::class, 'checkOpenSkyStatus']);
        // 3. TUS RUTAS EXISTENTES DE MÉTRICAS (Las mantenemos)
        Route::get('/admin/system/cpu-usage', [\App\Http\Controllers\Admin\SystemMetricsController::class, 'cpuUsage']);
        Route::get('/admin/system/memory-usage', [\App\Http\Controllers\Admin\SystemMetricsController::class, 'memoryUsage']);

        // POST porque estamos cambiando el estado del servidor
        Route::post('/admin/system/{action}', [AdminDashboardController::class, 'runSystemAction']);
    });



});