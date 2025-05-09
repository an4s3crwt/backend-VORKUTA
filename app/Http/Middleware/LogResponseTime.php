<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use App\Models\PerformanceLog;


class LogResponseTime
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $end = microtime(true);
    
        $executionTime = $end - $start;
    
        PerformanceLog::create([
            'method' => $request->method(),
            'path' => $request->path(),
            'response_time' => round($executionTime, 4),
            'status_code' => $response->status(),
        ]);
    
        return $response;
    }
}