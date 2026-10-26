<?php

namespace App\Http\Controllers;

use App\Services\FlightPatternService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FlightController extends Controller
{
    // =========================================================================
    // 🕵️ HELPER PRIVADO: REGISTRAR ACTIVIDAD
    // =========================================================================
    private function logAction($action, $details)
    {
        try {
            $userId = auth()->id(); 
            if ($userId) {
                DB::table('logs')->insert([
                    'user_id'    => $userId,
                    'action'     => $action,
                    'details'    => $details,
                    'ip_address' => request()->ip(),
                    'level'      => 'info',
                    'created_at' => now(),
                ]);
            }
        } catch (\Exception $e) {}
    }

    // =========================================================================
    // 1. GET FLIGHT DATA 
    // =========================================================================
public function getFlightData($icao)
    {
        $icao = strtolower(trim($icao));
        $cacheKey = "flight_live_{$icao}";

        // Servir desde caché si se pidió hace menos de 10 segundos
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $username = config('services.opensky.username') ?? env('OPENSKY_USERNAME');
        $password = config('services.opensky.password') ?? env('OPENSKY_PASSWORD');

        $client = Http::timeout(6);
        if (!empty($username) && !empty($password)) {
            $client = $client->withBasicAuth($username, $password);
        }

        try {
            $response = $client->get('https://opensky-network.org/api/states/all', [
                'icao24' => $icao
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['states']) && is_array($data['states'])) {
                    $this->logAction('view_flight', "Viendo avión: $icao");
                    Cache::put($cacheKey, $data, 10);
                    return response()->json($data);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("OpenSky fetch failed for {$icao}: " . $e->getMessage());
        }

        // Si el avión no está en el aire o la API no responde, devolvemos states vacío
        return response()->json([
            'time' => time(),
            'states' => []
        ], 200);
    }

    // =========================================================================
    // 2. PREDICT DELAY (MOTOR DETERMINISTA + BD)
    // =========================================================================
    public function predictFlightDelay(Request $request, FlightPatternService $patternService)
    {
        $telemetry = $request->input('flight_data', []);
        $icao = $telemetry['icao24'] ?? 'unknown';

        try {
            $evaluation = $patternService->evaluateFlight($telemetry);

            if ($evaluation['success']) {
                DB::table('ai_logs')->insert([
                    'flight_icao'   => $icao,
                    'prediction'    => $evaluation['status'],
                    'probability'   => $evaluation['predicted_probability'],
                    'delay_minutes' => $evaluation['delay_minutes'],
                    'reason'        => $evaluation['explanation'],
                    'created_at'    => now()
                ]);

                return response()->json($evaluation);
            }

            return response()->json(['success' => false, 'error' => 'Evaluation failed']);

        } catch (\Exception $e) {
            \Log::error("Analysis Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // OTRAS FUNCIONES
    // =========================================================================
    public function getAllFlights() {
        $username = config('services.opensky.username') ?? env('OPENSKY_USERNAME');
        $password = config('services.opensky.password') ?? env('OPENSKY_PASSWORD');

        $client = Http::timeout(10);
        if (!empty($username) && !empty($password)) {
            $client = $client->withBasicAuth($username, $password);
        }

        $r = $client->get('https://opensky-network.org/api/states/all');
        $this->logAction('map_load', "Carga inicial mapa");
        return response()->json($r->json() ?? ['states' => []]);
    }
    
    public function getFlightsByArea(Request $r) {
        $r->validate(['lamin'=>'required','lomin'=>'required','lamax'=>'required','lomax'=>'required']);

        $username = config('services.opensky.username') ?? env('OPENSKY_USERNAME');
        $password = config('services.opensky.password') ?? env('OPENSKY_PASSWORD');

        $client = Http::timeout(10);
        if (!empty($username) && !empty($password)) {
            $client = $client->withBasicAuth($username, $password);
        }

        $resp = $client->get('https://opensky-network.org/api/states/all', $r->all());
        $this->logAction('map_move', "Exploró zona");
        return response()->json($resp->json() ?? ['states' => []]);
    }

    public function getNearbyFlights(Request $r) {
        return response()->json(['msg'=>'ok']); 
    }
}