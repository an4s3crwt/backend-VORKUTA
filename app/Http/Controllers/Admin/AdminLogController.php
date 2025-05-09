<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PerformanceLog;
use Illuminate\Support\Facades\DB;

class AdminLogController extends Controller
{
    public function performanceStats()
{
    $logs = PerformanceLog::orderBy('created_at', 'desc')->limit(100)->get();

    $average = PerformanceLog::avg('response_time');

    return response()->json([
        'average_response_time' => round($average, 3),
        'logs' => $logs,
    ]);
}


 public function getApiMetrics()
    {
        // Query your logs or database where API success/error metrics are stored
        $successCount = DB::table('logs')->where('level', 'info')->count();
        $errorCount = DB::table('logs')->where('level', 'error')->count();
        $totalCount = $successCount + $errorCount;

        $successRate = $totalCount > 0 ? ($successCount / $totalCount) * 100 : 0;
        $errorRate = $totalCount > 0 ? ($errorCount / $totalCount) * 100 : 0;

        return response()->json([
            'success_rate' => $successRate,
            'error_rate' => $errorRate,
            'total_requests' => $totalCount,
            'successful_requests' => $successCount,
            'failed_requests' => $errorCount,
        ]);
    }
}
