<?php

namespace App\Http\Controllers;

use App\Services\FlightPatternService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB; 


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
    // 1. GET FLIGHT DATA (RÁPIDO ⚡) - Solo datos de OpenSky
    // =========================================================================
    public function getFlightData($icao)
    {
        // Esta función ahora es INMEDIATA. No espera a Python.
        $url = 'https://opensky-network.org/api/states/all';
        $response = Http::withBasicAuth(env('OPENSKY_USERNAME'), env('OPENSKY_PASSWORD'))
                        ->get($url, ['icao24' => $icao]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error OpenSky'], $response->status());
        }

        // Solo log de que el usuario miró el avión
        $this->logAction('view_flight', "Viendo avión: $icao");

        return response()->json($response->json());
    }

    // =========================================================================
    // 2. PREDICT DELAY (AHORA INSTANTÁNEO ⚡) - Motor Determinista + BD
    // =========================================================================
    public function predictFlightDelay(Request $request, FlightPatternService $patternService)
    {
        $telemetry = $request->input('flight_data', []);
        $icao = $telemetry['icao24'] ?? 'unknown';

        try {
            // Evaluación matemática en memoria (< 1 milisegundo)
            $evaluation = $patternService->evaluateFlight($telemetry);

            // Guardamos exactamente igual en la tabla ai_logs para tu Dashboard
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
    // OTRAS FUNCIONES (Mantener igual)
    // =========================================================================
    public function getAllFlights() {
        $r = Http::withBasicAuth(env('OPENSKY_USERNAME'), env('OPENSKY_PASSWORD'))->get('https://opensky-network.org/api/states/all');
        $this->logAction('map_load', "Carga inicial mapa");
        return response()->json($r->json());
    }
    
    public function getFlightsByArea(Request $r) {
        $r->validate(['lamin'=>'required','lomin'=>'required','lamax'=>'required','lomax'=>'required']);
        $resp = Http::withBasicAuth(env('OPENSKY_USERNAME'), env('OPENSKY_PASSWORD'))->get('https://opensky-network.org/api/states/all', $r->all());
        $this->logAction('map_move', "Exploró zona");
        return response()->json($resp->json());
    }

    public function getNearbyFlights(Request $r) {
        // Tu lógica de nearby simplificada o completa
        return response()->json(['msg'=>'ok']); 
    }
}