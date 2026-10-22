<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class OpenSkyController extends Controller
{
    /**
     * Instancia de Firebase bajo demanda para evitar errores de inyección
     */
    protected function getFirebaseAuth()
    {
        return app('firebase.auth');
    }

    /**
     * Método helper para validar Firebase UID
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
     * Método para obtener todos los estados de vuelos
     */
    public function getStatesAll(Request $request)
    {
        try {
            $params = [];

            if ($request->has(['lamin', 'lamax', 'lomin', 'lomax'])) {
                $params = [
                    'lamin' => $request->input('lamin'),
                    'lamax' => $request->input('lamax'),
                    'lomin' => $request->input('lomin'),
                    'lomax' => $request->input('lomax'),
                ];
            }

            // Claves de caché para evitar golpear a OpenSky en cada movimiento
            $cacheKey = 'opensky_bounds_' . md5(json_encode($params));
            $globalFallbackKey = 'opensky_last_known_flights';

            // 1. Si tenemos datos cacheados para esta zona (menos de 10 seg), los servimos
            if (Cache::has($cacheKey)) {
                return response()->json(Cache::get($cacheKey), 200);
            }

            $username = config('services.opensky.username') ?? env('OPENSKY_USERNAME');
            $password = config('services.opensky.password') ?? env('OPENSKY_PASSWORD');

            // --- LOG DE DEPURACIÓN DE CREDENCIALES ---
            Log::info('OpenSky auth check:', [
                'has_user' => !empty($username),
                'user_length' => strlen($username ?? ''),
                'has_pass' => !empty($password),
                'has_bounds' => !empty($params),
            ]);

            $client = Http::timeout(10);
            if (!empty($username) && !empty($password)) {
                $client = $client->withBasicAuth($username, $password);
            }

            $response = $client->get('https://opensky-network.org/api/states/all', $params);

            // 2. Si OpenSky da 429 (Too Many Requests) o falla:
            if ($response->status() === 429 || $response->failed()) {
                Log::warning('OpenSky devolvió ' . $response->status() . ' - Body: ' . $response->body());

                if (Cache::has($globalFallbackKey)) {
                    return response()->json(Cache::get($globalFallbackKey), 200);
                }

                return response()->json([
                    'time' => time(),
                    'states' => [],
                    'warning' => 'OpenSky rate limit hit. Reintentando...'
                ], 200);
            }

            $data = $response->json();

            if (!isset($data['states']) || !is_array($data['states'])) {
                Log::warning('OpenSky devolvió states nulo o vacío', ['response' => $data]);
                return response()->json(['time' => time(), 'states' => []], 200);
            }

            // Filtrar aviones válidos con coordenadas
            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return !empty($flight[0]) && isset($flight[5], $flight[6]) && $flight[5] !== null && $flight[6] !== null;
                })
                ->map(function ($flight) {
                    if (empty(trim($flight[1] ?? ''))) {
                        $flight[1] = 'UNKNOWN';
                    }
                    return $flight;
                })
                ->take(300)
                ->values()
                ->all();

            $payload = [
                'time' => $data['time'] ?? time(),
                'states' => $filteredStates,
            ];

            // Guardamos en caché local (10 segundos) y global de respaldo (2 minutos)
            Cache::put($cacheKey, $payload, 10);
            Cache::put($globalFallbackKey, $payload, 120);

            return response()->json($payload, 200);

        } catch (\Throwable $e) {
            Log::error('getStatesAll fatal error: ' . $e->getMessage());

            if (Cache::has('opensky_last_known_flights')) {
                return response()->json(Cache::get('opensky_last_known_flights'), 200);
            }

            return response()->json(['time' => time(), 'states' => []], 200);
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
                    return $flight[0] && $flight[1] && $flight[5] && $flight[6];
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