<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB; 
use Symfony\Component\Process\Process; 
use Symfony\Component\Process\Exception\ProcessFailedException;

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
    // 2. PREDICT DELAY (LENTO 🐢) - Ejecuta IA y Guarda en BD
    // =========================================================================
    public function predictFlightDelay(Request $request)
    {
        // Recibimos los datos que nos envía React
        $telemetry = $request->input('flight_data');
        $icao = $telemetry['icao24'] ?? 'unknown';

        // Preparamos datos para Python (Mapeo limpio para Pandas)
        $features = [
            'latitude'      => $telemetry['latitude'] ?? 0.0,
            'longitude'     => $telemetry['longitude'] ?? 0.0,
            'velocity'      => $telemetry['velocity'] ?? 0.0,
            'heading'       => $telemetry['heading'] ?? 0.0,
            'baro_altitude' => $telemetry['baro_altitude'] ?? 0.0,
            'geo_altitude'  => $telemetry['geo_altitude'] ?? 0.0,
            'vertical_rate' => $telemetry['vertical_rate'] ?? 0.0,
            'on_ground'     => $telemetry['on_ground'] ? true : false
        ];

        try {
            // Ejecutamos Python
            $scriptPath = storage_path('app/scripts/predict_delay.py');
            
            // Pasamos los datos como un array JSON
            $process = new Process(['python3', $scriptPath, json_encode([$features])]);
            $process->setTimeout(20); // Damos tiempo a la IA
            $process->run();

            if ($process->isSuccessful()) {
                $aiData = json_decode($process->getOutput(), true);

                // GUARDAMOS EN LA TABLA AI_LOGS (Para que salga en tu Dashboard)
                if (isset($aiData['success']) && $aiData['success']) {
                    DB::table('ai_logs')->insert([
                        'flight_icao'   => $icao,
                        'prediction'    => $aiData['status'],
                        'probability'   => $aiData['predicted_probability'],
                        'delay_minutes' => $aiData['delay_minutes'],
                        'reason'        => $aiData['explanation'],
                        'created_at'    => now()
                    ]);
                    
                    return response()->json($aiData); // Devolvemos el resultado al frontend
                }
            }
            
            return response()->json(['success' => false, 'error' => 'AI execution failed']);

        } catch (\Exception $e) {
            \Log::error("AI Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
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