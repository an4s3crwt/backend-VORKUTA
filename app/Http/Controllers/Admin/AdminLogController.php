<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PerformanceLog;

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
}
