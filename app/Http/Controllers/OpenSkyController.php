<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class OpenSkyController extends Controller
{
    public function getStatesAll(Request $request)
    {
        try {
            // Obtener usuario de Firebase desde la request (set en el middleware)
            $user = $request->get('firebase_user');

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $data = $this->fetchLiveData();
            if (!$data) {
                return response()->json([
                    'error' => 'Error al obtener datos de OpenSky'
                ], 500);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error en el servidor',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function fetchLiveData()
    {
        try {
            $username = config('services.opensky.username');
            $password = config('services.opensky.password');

            $response = Http::withBasicAuth($username, $password)
                ->timeout(30)
                ->get('https://opensky-network.org/api/states/all');

            if ($response->failed()) {
                return null;
            }

            $data = $response->json();
            // Filtra antes de devolver al frontend
            if (!isset($data['states'])) {
                return null;
            }

            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return $flight[0] && $flight[1] && $flight[5] && $flight[6];
                })
                ->take(200)
                ->values()
                ->all();

            return [
                'time' => $data['time'],
                'states' => $filteredStates,
            ];
        } catch (\Exception $e) {
            return null;
        }
    } //cambiar este metodo / crear uno nuevo para scanner, que no consuma de este 
}
