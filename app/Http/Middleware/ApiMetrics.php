<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiMetrics
{
    public function handle(Request $request, Closure $next)
    {
        // Log request details before executing the API logic
        $response = $next($request);

        // Track success
        if ($response->status() >= 200 && $response->status() < 300) {
            Log::info('API Success: ' . $request->url());
        }

        // Track errors
        if ($response->status() >= 400) {
            Log::error('API Error: ' . $request->url() . ' - Status Code: ' . $response->status());
        }

        return $response;
    }
}

