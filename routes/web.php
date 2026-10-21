<?php

use Laravel\Telescope\Telescope;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'online',
        'app' => 'Flighty API',
        'database' => 'PostgreSQL (Neon)',
        'timestamp' => now()->toIso8601String()
    ]);
});

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// CSRF para Sanctum

Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
});





// Registra las rutas de Telescope solo si está habilitado y el usuario tiene permiso
if (config('telescope.enabled')) {
    // In newer versions, Telescope routes are auto-registered in the TelescopeServiceProvider.
    // Ensure the TelescopeServiceProvider is properly configured.
}
