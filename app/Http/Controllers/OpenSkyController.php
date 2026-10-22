<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class OpenSkyController extends Controller
{
    /**
     * Obtener estados de vuelos con caché temporal para OpenSky
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

            // Clave de caché única según coordenadas (10 segundos para no saturar OpenSky)
            $cacheKey = 'opensky_' . md5(json_encode($params));

            $data = Cache::remember($cacheKey, 10, function () use ($params) {
                $username = config('services.opensky.username');
                $password = config('services.opensky.password');

                $requestHttp = Http::timeout(10);
                if (!empty($username) && !empty($password)) {
                    $requestHttp = $requestHttp->withBasicAuth($username, $password);
                }

                $response = $requestHttp->get('https://opensky-network.org/api/states/all', $params);

                if ($response->failed()) {
                    Log::warning('OpenSky API failed with status: ' . $response->status());
                    return null;
                }

                return $response->json();
            });

            if (!$data || !isset($data['states'])) {
                return response()->json([
                    'time' => time(),
                    'states' => [],
                    'warning' => 'OpenSky rate limit or temporary upstream unavailability'
                ], 200);
            }

            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return !empty($flight[0]) && !empty($flight[1]) && isset($flight[5], $flight[6]);
                })
                ->take(200)
                ->values()
                ->all();

            return response()->json([
                'time' => $data['time'] ?? time(),
                'states' => $filteredStates,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('getStatesAll error: ' . $e->getMessage());
            return response()->json([
                'time' => time(),
                'states' => [],
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Datos en vivo
     */
    public function fetchLiveData(Request $request)
    {
        return $this->getStatesAll($request);
    }

    /**
     * Escaneo de datos
     */
    public function scanLiveData(Request $request)
    {
        return $this->getStatesAll($request);
    }
}