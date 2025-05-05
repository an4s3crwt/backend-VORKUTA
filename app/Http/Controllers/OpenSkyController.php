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

            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }
}
