<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OpenSkyController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SavedFlight; 
use Illuminate\Support\Facades\DB;


class AdminMetricsController extends Controller
{
  /**
     * Mostrar las métricas del sistema.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Obtener los datos en vivo desde OpenSkyController
            $openSkyController = new OpenSkyController();
            $liveData = $openSkyController->fetchLiveData();

            // Verificar si los datos fueron obtenidos correctamente
            if (!$liveData || !isset($liveData['states'])) {
                return response()->json(['error' => 'No se pudieron obtener datos de OpenSky'], 500);
            }

            // Contar el número de vuelos activos
            $totalFlights = count($liveData['states']); // Esto da el número de vuelos activos

            // Obtener el número total de usuarios desde la base de datos
            $totalUsers = User::count();

            // Obtener los usuarios activos (por ejemplo, los que se han conectado en los últimos 30 días)
            // Aquí puedes usar tu propia lógica para determinar qué significa "activo"
            $activeUsers = User::where('last_activity', '>=', now()->subDays(30))->count();

            return response()->json([
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'total_flights' => $totalFlights,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener las métricas: ' . $e->getMessage()], 500);
        }
    }
}
