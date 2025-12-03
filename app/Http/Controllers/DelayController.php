<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\FlightPosition; // <--- IMPORTANTE

class DelayController extends Controller
{
    public function predict(Request $request)
    {
        $inputData = $request->input('flight_data');
        
        if (!$inputData || !isset($inputData['icao24'])) { // Necesitamos el ICAO para guardar
            // Si viene del mapa individual a veces falta el icao en el body, 
            // asegúrate de enviarlo desde el frontend o usar uno temporal.
            return response()->json(['success' => false, 'message' => 'Falta ICAO24'], 400);
        }

        // 1. GUARDAR LA POSICIÓN ACTUAL EN LA BASE DE DATOS
       FlightPosition::create([
    'icao24'        => $inputData['icao24'],
    'latitude'      => $inputData['latitude'] ?? 0,
    'longitude'     => $inputData['longitude'] ?? 0,
    'velocity'      => $inputData['velocity'] ?? 0,
    'heading'       => $inputData['heading'] ?? 0,
    'baro_altitude' => $inputData['baro_altitude'] ?? 0, // <--- El truco '?? 0'
    'geo_altitude'  => $inputData['geo_altitude'] ?? 0,
    'on_ground'     => $inputData['on_ground'] ?? false,
    'vertical_rate' => $inputData['vertical_rate'] ?? 0
]);

        // 2. RECUPERAR EL HISTORIAL (Los últimos 20 puntos, ordenados del más viejo al nuevo)
        $history = FlightPosition::where('icao24', $inputData['icao24'])
                    ->orderBy('created_at', 'desc')
                    ->limit(20) // 20 puntos son suficientes para calcular tendencias
                    ->get()
                    ->reverse() // Python necesita orden cronológico
                    ->values()
                    ->toArray();

        // 3. ENVIAR HISTORIAL A PYTHON
        $pythonScriptPath = base_path('app/ml/predict_flight.py');
        
        // Ahora enviamos una LISTA de puntos, no uno solo
        $process = new Process(['python3', $pythonScriptPath, json_encode($history)]);
        
        try {
            $process->mustRun();
            return response()->json(json_decode($process->getOutput(), true));
        } catch (ProcessFailedException $exception) {
            return response()->json([
                'success' => false, 
                'error' => $exception->getMessage()
            ], 500);
        }
    }
}