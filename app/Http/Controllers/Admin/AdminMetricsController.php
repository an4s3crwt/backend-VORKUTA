<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SavedFlight; 
use Illuminate\Support\Facades\DB;


class AdminMetricsController extends Controller
{
    public function index()
    {
        return response()->json([
            'total_users' => User::count(),
            'active_users_last_week' => User::where('last_login', '>=', now()->subWeek())->count(),
            'saved_flights' => SavedFlight::count(),
            'top_flights' => SavedFlight::select('flight_icao', DB::raw('count(*) as total'))
                                ->groupBy('flight_icao')
                                ->orderByDesc(column: 'total')
                                ->limit(5)
                                ->get()->toArray(),
            'top_airports' => SavedFlight::select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(flight_data, '$.departure_airport')) AS airport"), DB::raw('count(*) as total'))
                                ->groupBy('airport')
                                ->orderByDesc('total')
                                ->limit(5)
                                ->get()->toArray(),
        ]);
    }
}
