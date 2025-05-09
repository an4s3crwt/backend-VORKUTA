<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AdminMetricsController extends Controller
{
    public function index()
    {
        // Total de vuelos únicos (por callsign)
        $totalFlights = DB::table('flight_views')
            ->select('callsign')
            ->distinct()
            ->count();

        // Total de usuarios
        $totalUsers = DB::table('users')->count();

        // Total de visualizaciones de vuelos
        $totalViews = DB::table('flight_views')->count();

        // Usuarios con más visualizaciones
        $topUsers = DB::table('flight_views')
            ->join('users', 'flight_views.firebase_uid', '=', 'users.firebase_uid')
            ->select('users.email as name', DB::raw('COUNT(*) as total_views'))
            ->groupBy('users.email')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get();

        // Rutas más vistas
        $topRoutes = DB::table('flight_views')
            ->select('from_airport_code', 'to_airport_code', DB::raw('COUNT(*) as views'))
            ->groupBy('from_airport_code', 'to_airport_code')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        return response()->json([
            'flights' => [
                'total_views' => $totalViews,
                'by_user' => $topUsers,
                'top_routes' => $topRoutes,
                'total' => $totalFlights,
            ],
            'total_users' => $totalUsers,
        ]);
    }
}
