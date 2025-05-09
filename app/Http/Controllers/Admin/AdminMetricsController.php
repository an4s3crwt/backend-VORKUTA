<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Auth as FirebaseAuth;

class AdminMetricsController extends Controller
{
    protected $auth;

    // Inyectamos el servicio de Firebase Auth
    public function __construct(FirebaseAuth $auth)
    {
        $this->auth = $auth;
    }

    public function index()
    {
        try {
            // Total de vuelos únicos (por callsign)
            $totalFlights = DB::table('flight_views')
                ->select('callsign')
                ->distinct()
                ->count();

            // Total de usuarios en Firebase
            $firebaseUsers = collect($this->auth->listUsers(1000)); // Limite de 1000 usuarios
            $totalUsers = $firebaseUsers->count();

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

            \Log::info('Total Flights:', [$totalFlights]);
            \Log::info('Total Views:', [$totalViews]);
            \Log::info('Top Users:', [$topUsers]);
            \Log::info('Top Routes:', [$topRoutes]);

            return response()->json([
                'flights' => [
                    'total_views' => $totalViews,
                    'by_user' => $topUsers,
                    'top_routes' => $topRoutes,
                    'total' => $totalFlights,
                ],
                'total_users' => $totalUsers,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener las métricas: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener las métricas.'], 500);
        }
    }
}
