<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class OpenSkyController extends Controller
{
    /**
     * Obtenemos Firebase bajo demanda para que Laravel no explote al instanciarlo
     */
    protected function getFirebaseAuth()
    {
        return app('firebase.auth');
    }

    /**
     * Validar Firebase UID (Tu método original intacto)
     */
    public function validateFirebaseUid(Request $request)
    {
        $uid = $request->input('uid');

        try {
            $user = $this->getFirebaseAuth()->getUser($uid);
            return response()->json(['user' => $user], 200);
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            Log::error('User not found in Firebase with UID: ' . $uid);
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching Firebase user: ' . $e->getMessage());
            return response()->json(['error' => 'Error fetching Firebase user'], 500);
        }
    }

    /**
     * Obtener estados de vuelos para el mapa
     */
    public function getStatesAll(Request $request)
    {
        try {
            $params = [];

            // Si el frontend envía las coordenadas del cuadro visible del mapa
            if ($request->has(['lamin', 'lamax', 'lomin', 'lomax'])) {
                $params = [
                    'lamin' => $request->input('lamin'),
                    'lamax' => $request->input('lamax'),
                    'lomin' => $request->input('lomin'),
                    'lomax' => $request->input('lomax'),
                ];
            }

            // Credenciales usando config() para que no fallen con config:cache
            $username = config('services.opensky.username') ?? env('OPENSKY_USERNAME');
            $password = config('services.opensky.password') ?? env('OPENSKY_PASSWORD');

            $client = Http::timeout(15);
            if (!empty($username) && !empty($password)) {
                $client = $client->withBasicAuth($username, $password);
            }

            $response = $client->get('https://opensky-network.org/api/states/all', $params);

            if ($response->failed()) {
                Log::error('OpenSky API failed with status: ' . $response->status());
                return response()->json([
                    'error' => 'Failed to fetch flight data', 
                    'status' => $response->status()
                ], $response->status() === 429 ? 429 : 502);
            }

            $data = $response->json();

            if (!isset($data['states']) || !is_array($data['states'])) {
                return response()->json([
                    'time' => time(),
                    'states' => []
                ], 200);
            }

            // Filtro flexible: solo exige que el avión tenga ICAO y coordenadas GPS válidas
            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return !empty($flight[0]) && isset($flight[5]) && isset($flight[6]) && $flight[5] !== null && $flight[6] !== null;
                })
                ->map(function ($flight) {
                    // Si el callsign viene vacío o nulo, le ponemos un valor legible para que el frontend no falle al pintar
                    if (empty(trim($flight[1] ?? ''))) {
                        $flight[1] = 'NO-CALLSIGN';
                    }
                    return $flight;
                })
                ->take(300)
                ->values()
                ->all();

            return response()->json([
                'time' => $data['time'] ?? time(),
                'states' => $filteredStates,
            ]);

        } catch (\Exception $e) {
            Log::error('getStatesAll error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Método para obtener datos en tiempo real filtrados
     */
    public function fetchLiveData(Request $request)
    {
        try {
            $username = config('services.opensky.username') ?? env('OPENSKY_USERNAME');
            $password = config('services.opensky.password') ?? env('OPENSKY_PASSWORD');

            $client = Http::timeout(20);
            if (!empty($username) && !empty($password)) {
                $client = $client->withBasicAuth($username, $password);
            }

            $response = $client->get('https://opensky-network.org/api/states/all');

            if ($response->failed()) {
                Log::warning('OpenSky API failed with status: ' . $response->status());
                return response()->json(['error' => 'Service unavailable'], 503);
            }

            $data = $response->json();

            if (!isset($data['states'])) {
                return response()->json(['error' => 'Invalid data format'], 500);
            }

            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return !empty($flight[0]) && isset($flight[5], $flight[6]) && $flight[5] !== null && $flight[6] !== null;
                })
                ->take(200)
                ->values()
                ->all();

            return response()->json([
                'time' => $data['time'],
                'states' => $filteredStates,
            ]);

        } catch (\Exception $e) {
            Log::error('fetchLiveData error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Método para escanear datos en segundo plano
     */
    public function scanLiveData(Request $request)
    {
        try {
            $username = config('services.opensky.username') ?? env('OPENSKY_USERNAME');
            $password = config('services.opensky.password') ?? env('OPENSKY_PASSWORD');

            $client = Http::timeout(30);
            if (!empty($username) && !empty($password)) {
                $client = $client->withBasicAuth($username, $password);
            }

            $response = $client->get('https://opensky-network.org/api/states/all');

            if ($response->failed()) {
                Log::warning('OpenSky scan failed with status: ' . $response->status());
                return response()->json(['error' => 'Service unavailable'], 503);
            }

            $data = $response->json();

            if (!isset($data['states'])) {
                return response()->json(['error' => 'Invalid data format'], 500);
            }

            return response()->json([
                'message' => 'Scan completed successfully',
                'count' => count($data['states']),
                'timestamp' => $data['time']
            ]);

        } catch (\Exception $e) {
            Log::error('scanLiveData error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}