<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class LogResponseTime
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Marcar tiempo de inicio
        $startTime = microtime(true);

        // 2. Dejar que la aplicación procese la petición (Controller, etc.)
        $response = $next($request);

        // 3. Calcular duración en milisegundos
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // 4. Guardar en la tabla 'performance_logs'
        // Solo guardamos peticiones API, ignoramos cosas internas
        if ($request->is('api/*')) {
            try {
                DB::table('performance_logs')->insert([
                    'endpoint' => $request->path(),
                    'method'   => $request->method(),
                    'duration_ms' => $duration,
                    'status_code' => $response->getStatusCode(),
                    'ip_address'  => $request->ip(),
                    'created_at'  => now(),
                ]);
            } catch (\Exception $e) {
                // Si falla el log, no rompemos la app, solo lo ignoramos
            }
        }

        return $response;
    }
}