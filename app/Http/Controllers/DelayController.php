<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DelayController extends Controller
{
    // Historial mínimo por vuelo
    private static $flight_history = [];

    public function predict(Request $request)
    {
        $data = $request->input('features');
        if (!$data || !isset($data['icao24'])) {
            return response()->json([
                'message' => 'Error en el motor de predicción.',
                'details' => 'Faltan datos críticos: icao24'
            ], 400);
        }

        $icao = $data['icao24'];
        if (!isset(self::$flight_history[$icao])) {
            self::$flight_history[$icao] = [];
        }

        // Guardar posición actual y asegurar historial mínimo
        $history = self::$flight_history[$icao];
        $history = $this->ensureHistory($history, $data);
        self::$flight_history[$icao] = $history;

        // Calcular features dinámicas
        $features = $this->computeFeatures($history);

        // Validación de todas las columnas críticas
        $required_features = [
            'latitude','longitude','velocity','heading','baro_altitude','geo_altitude',
            'heading_change','heading_change_cumsum','dist_moved','velocity_mean',
            'altitude_mean','velocity_std','altitude_std','vertical_rate_std',
            'hour_sin','hour_cos','phase_air','phase_ground'
        ];

        foreach ($required_features as $col) {
            if (!array_key_exists($col, $features)) {
                return response()->json([
                    'message' => 'Error en el motor de predicción.',
                    'details' => "Falta columna crítica: $col"
                ], 400);
            }
        }

        // Ejecutar script Python
        $python_script_path = '/root/VORKUTA/FR/app/ml/predict_flight.py';
        $command = "python3 $python_script_path " . escapeshellarg(json_encode(['features' => $features]));

        try {
            $output_json = shell_exec($command);
            if (!$output_json) {
                throw new \Exception("Fallo al ejecutar Python");
            }
            $result = json_decode($output_json, true);
            if (isset($result['error'])) {
                Log::error("ML Prediction Error: ".$result['error']);
                return response()->json([
                    'message'=>'Error en el motor de predicción.',
                    'details'=>$result['error']
                ],500);
            }
            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error("Backend Error on Prediction: ".$e->getMessage());
            return response()->json([
                'message'=>'Error interno del servidor.',
                'details'=>$e->getMessage()
            ],500);
        }
    }

    // Asegura mínimo historial de 3 posiciones, genera puntos ficticios si es necesario
    private function ensureHistory($history, $current) {
        $needed = 3; // mínimo 3 puntos para las features
        $n = count($history);
        if ($n >= $needed) return array_merge($history, [$current]);

        $newHistory = $history;
        for ($i = $n; $i < $needed-1; $i++) {
            $prev = $current;
            // Genera punto ficticio con pequeñas variaciones
            $fake = [
                'latitude' => $prev['latitude'] + rand(-10,10)/1000,
                'longitude' => $prev['longitude'] + rand(-10,10)/1000,
                'velocity' => $prev['velocity'] * (0.95 + rand(0,10)/100),
                'heading' => ($prev['heading'] + rand(-5,5)) % 360,
                'geo_altitude' => $prev['geo_altitude'] * (0.95 + rand(0,10)/100),
                'baro_altitude' => $prev['baro_altitude'] * (0.95 + rand(0,10)/100),
                'vertrate' => $prev['vertrate'] + rand(-2,2),
                'phase' => $prev['phase'],
                'time_position' => $prev['time_position'] - ($needed-$i-1)*10
            ];
            $newHistory[] = $fake;
        }
        $newHistory[] = $current;
        return $newHistory;
    }

    // Calcula todas las features requeridas para el ML
    private function computeFeatures($history)
    {
        $n = count($history);
        if ($n === 0) return [];

        $last = $history[$n-1];

        $heading_change = 0;
        $heading_cumsum = 0;
        $dist_moved = 0;
        $velocities = [];
        $altitudes = [];
        $vertical_rates = [];

        for ($i=0; $i<$n; $i++) {
            $velocities[] = $history[$i]['velocity'] ?? 0;
            $altitudes[] = $history[$i]['geo_altitude'] ?? 0;
            $vertical_rates[] = $history[$i]['vertrate'] ?? 0;
            if ($i>0) {
                $delta_heading = abs($history[$i]['heading'] - $history[$i-1]['heading']);
                $heading_change = $delta_heading;
                $heading_cumsum += $delta_heading;

                $lat1 = $history[$i-1]['latitude'];
                $lon1 = $history[$i-1]['longitude'];
                $lat2 = $history[$i]['latitude'];
                $lon2 = $history[$i]['longitude'];
                $dist_moved += $this->haversine($lat1,$lon1,$lat2,$lon2);
            }
        }

        $hour = date('G', $last['time_position'] ?? time());

        return [
            'latitude' => $last['latitude'] ?? 0,
            'longitude' => $last['longitude'] ?? 0,
            'velocity' => $last['velocity'] ?? 0,
            'heading' => $last['heading'] ?? 0,
            'baro_altitude' => $last['baro_altitude'] ?? 0,
            'geo_altitude' => $last['geo_altitude'] ?? 0,
            'heading_change' => $heading_change,
            'heading_change_cumsum' => $heading_cumsum,
            'dist_moved' => $dist_moved,
            'velocity_mean' => array_sum($velocities)/count($velocities),
            'altitude_mean' => array_sum($altitudes)/count($altitudes),
            'velocity_std' => $this->stddev($velocities),
            'altitude_std' => $this->stddev($altitudes),
            'vertical_rate_std' => $this->stddev($vertical_rates),
            'hour_sin' => sin(2 * pi() * $hour/24),
            'hour_cos' => cos(2 * pi() * $hour/24),
            'phase_air' => ($last['phase'] ?? 'air') === 'air' ? 1 : 0,
            'phase_ground' => ($last['phase'] ?? 'air') === 'ground' ? 1 : 0
        ];
    }

    private function stddev($arr)
    {
        $mean = array_sum($arr)/count($arr);
        $sum = 0;
        foreach($arr as $v) $sum += pow($v - $mean,2);
        return sqrt($sum/count($arr));
    }

    private function haversine($lat1,$lon1,$lat2,$lon2)
    {
        $R = 6371; // km
        $dLat = deg2rad($lat2-$lat1);
        $dLon = deg2rad($lon2-$lon1);
        $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2);
        $c = 2*atan2(sqrt($a), sqrt(1-$a));
        return $R*$c*1000; // metros
    }
}
