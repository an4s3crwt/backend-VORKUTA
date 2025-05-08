<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OpenSkyController extends Controller
{
    public function getStatesAll()
    {
        $response = Http::withBasicAuth(env('OPENSKY_USERNAME'), env('OPENSKY_PASSWORD'))
            ->get('https://opensky-network.org/api/states/all');

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch flight data'], 500);
        }

        $data = $response->json();


        $filteredStates = collect($data['states'])
        ->filter(function ($flight) {
            return $flight[0] && $flight[1] && $flight[5] && $flight[6]; // ICAO24, callsign, lat, lon
        })
        ->take(200)
        ->values()
        ->all();
    


        return response()->json([
            'time' => $data['time'] ?? time(),
            'states' => array_values($filteredStates), // reindexar
        ]);
    }

    /**
     * Método para frontend: retorna datos en tiempo real ya filtrados y reducidos
     */
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
            if (!isset($data['states'])) {
                return null;
            }

            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return $flight[0] && $flight[1] && $flight[5] && $flight[6]; // ICAO24, callsign, lat, lon
                })
                ->take(200)
                ->values()
                ->all();

            return [
                'time' => $data['time'],
                'states' => $filteredStates,
            ];
        } catch (\Exception $e) {
            Log::error('Error en fetchLiveData: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Método interno para escanear datos en segundo plano (por ejemplo: guardarlos en la DB)
     */
    public function scanLiveData()
    {
        try {
            $username = config('services.opensky.username');
            $password = config('services.opensky.password');

            $response = Http::withBasicAuth($username, $password)
                ->timeout(30)
                ->get('https://opensky-network.org/api/states/all');

            if ($response->failed()) {
                Log::warning('Fallo al escanear datos de OpenSky');
                return response()->json(['error' => 'No se pudo escanear OpenSky'], 500);
            }

            $data = $response->json();
            if (!isset($data['states'])) {
                return response()->json(['error' => 'Formato inesperado'], 500);
            }

            // Aquí puedes guardar en la DB si lo deseas (ejemplo: crear registros de vuelos)
            foreach ($data['states'] as $state) {
                if (!$state[0] || !$state[1]) {
                    continue;
                }

                // Lógica de almacenamiento personalizada
                // FlightPosition::create([...]);
            }

            return response()->json([
                'message' => 'Scan completo',
                'count' => count($data['states']),
                'timestamp' => $data['time']
            ]);
        } catch (\Exception $e) {
            Log::error('Error en scanLiveData: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno en escaneo'], 500);
        }
    }
}
