<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Flight;
use Illuminate\Support\Facades\Log;

class UpdateFlightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const AVERAGE_SPEED_KPH = 850; // Velocidad promedio configurable

    private function haversine($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function handle()
    {
        $username = env('OPENSKY_USERNAME');
        $password = env('OPENSKY_PASSWORD');

        $response = Http::withBasicAuth($username, $password)
            ->get('https://opensky-network.org/api/states/all');

        if ($response->failed()) {
            Log::error('Fallo la consulta a OpenSky');
            return;
        }

        $states = $response->json()['states'] ?? [];

        // Limitar la cantidad de vuelos por petición para no sobrecargar
        $states = array_slice($states, 0, 2000);

        foreach ($states as $flightData) {
            $icao24 = strtolower($flightData[0]);
            $callsign = trim($flightData[1]);
            $origin_country = $flightData[2];
            $last_contact = $flightData[4];
            $longitude = $flightData[5];
            $latitude = $flightData[6];
            $altitude = $flightData[7];
            $on_ground = $flightData[8];
            $velocity = $flightData[9];

            Log::info("Procesando vuelo: $icao24 ($callsign) - País: $origin_country");

            $flight = new Flight();
            $flight->icao24 = $icao24;
            $flight->callsign = $callsign;
            $flight->origin_country = $origin_country;
            $flight->last_contact = $last_contact;

            if (is_numeric($latitude)) $flight->last_latitude = $latitude;
            if (is_numeric($longitude)) $flight->last_longitude = $longitude;
            $flight->last_altitude = $altitude;
            $flight->last_on_ground = $on_ground;
            $flight->last_velocity = $velocity;

            // Como quieres solo insertar, no actualizar, no chequeamos ni modificamos otros campos aquí,
            // esos cálculos los harás después procesando la base de datos.

            // Guardamos vuelo sin actualizar previos
            $flight->save();

            Log::info("Vuelo guardado: $callsign");
        }
    }
}
