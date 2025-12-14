<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogPerformance
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if ($request->is('api/*')) {
            try {
                // AQUÍ ESTÁ EL CAMBIO: Usamos TUS columnas
                DB::table('performance_logs')->insert([
                    'method'        => $request->method(),
                    'path'          => $request->path(),       // Antes 'endpoint'
                    'response_time' => $duration,              // Antes 'duration_ms'
                    'status_code'   => $response->getStatusCode(),
                    'created_at'    => now(),
                    'updated_at'    => now()
                ]);
            } catch (\Exception $e) {
                // Silenciamos errores de log
            }
        }

        return $response;
    }
}