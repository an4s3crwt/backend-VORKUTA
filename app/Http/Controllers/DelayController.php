<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FlightPosition;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // <-- AÑADIR ESTE IMPORT

class DelayController extends Controller
{
    public function predict(Request $request)
    {
        $inputData = $request->input('flight_data');

        if (!$inputData || !isset($inputData['icao24'])) {
            return response()->json([
                'success' => false,
                'message' => 'Falta ICAO24 en flight_data'
            ], 400);
        }

        // 1. PERSISTENCIA DE TELEMETRÍA
        try {
            FlightPosition::create([
                'icao24'        => $inputData['icao24'],
                'latitude'      => $inputData['latitude'] ?? 0,
                'longitude'     => $inputData['longitude'] ?? 0,
                'velocity'      => $inputData['velocity'] ?? 0,
                'heading'       => $inputData['heading'] ?? 0,
                'baro_altitude' => $inputData['baro_altitude'] ?? 0,
                'geo_altitude'  => $inputData['geo_altitude'] ?? 0,
                'on_ground'     => $inputData['on_ground'] ?? false,
                'vertical_rate' => $inputData['vertical_rate'] ?? 0
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la posición: ' . $e->getMessage());
        }

        // 2. EXTRACCIÓN DE VARIABLES CINEMÁTICAS
        $velocity     = (float) ($inputData['velocity'] ?? 0);
        $altitude     = (float) ($inputData['geo_altitude'] ?? $inputData['baro_altitude'] ?? 0);
        $verticalRate = (float) ($inputData['vertical_rate'] ?? 0);
        $onGround     = (bool)  ($inputData['on_ground'] ?? false);

        $speedKnots = $velocity * 1.94384; 
        $altitudeFt = $altitude * 3.28084;

        // 3. MODELO DETERMINISTA DE RETRASO Y HOLDING
        $delayMinutes = 0.0;
        $status = 'on_time';
        $explanation = 'Operating within optimal flight envelope.';
        $probability = 0.05;

        if ($onGround || ($speedKnots < 30 && $altitudeFt < 500)) {
            $status = 'scheduled';
            $explanation = $speedKnots < 5 ? 'Aircraft parked at gate.' : 'Aircraft taxiing / ground operations.';
            $probability = 0.0;
            $delayMinutes = 0.0;

        } elseif ($altitudeFt < 10000 && $verticalRate < -1.5) {
            $status = 'on_time';
            $explanation = 'Standard descent and approach profile.';
            $probability = 0.15;
            $delayMinutes = 0.0;

        } elseif ($altitudeFt > 25000) {
            $expectedMinCruiseSpeed = 380; 
            if ($speedKnots < $expectedMinCruiseSpeed && $speedKnots > 100) {
                $speedDeficit = ($expectedMinCruiseSpeed - $speedKnots) / $expectedMinCruiseSpeed;
                $delayMinutes = round($speedDeficit * 25, 1);
                $probability = min(0.85, 0.3 + ($speedDeficit * 0.7));
                $status = $delayMinutes > 10 ? 'delayed' : 'potential_delay';
                $explanation = "Sub-optimal cruise speed ({$speedKnots} kts). Minor route delay expected.";
            } else {
                $status = 'on_time';
                $explanation = 'Cruise parameters nominal.';
                $probability = 0.05;
                $delayMinutes = 0.0;
            }

        } else {
            if ($verticalRate >= -0.5 && $verticalRate <= 0.5 && $speedKnots < 250) {
                $status = 'potential_delay';
                $delayMinutes = 12.0;
                $probability = 0.60;
                $explanation = 'Holding pattern or vectoring detected.';
            }
        }

        // 4. PERSISTENCIA DE LA PREDICCIÓN EN AUDITORÍA
        try {
            DB::table('ai_logs')->insert([
                'flight_icao'   => $inputData['icao24'],
                'prediction'    => $status,
                'probability'   => round((float) $probability, 2),
                'delay_minutes' => (float) $delayMinutes,
                'reason'        => $explanation,
                'created_at'    => now()
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar en ai_logs: ' . $e->getMessage());
        }

        // 5. RESPUESTA AL FRONTEND
        return response()->json([
            'success'               => true,
            'delay_minutes'         => (float) $delayMinutes,
            'status'                => $status,
            'predicted_probability' => round((float) $probability, 2),
            'explanation'           => $explanation,
            'calculated_at'         => now()->toIso8601String()
        ]);
    }
}