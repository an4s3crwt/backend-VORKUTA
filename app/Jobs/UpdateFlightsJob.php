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

            $flight = Flight::firstOrNew(['icao24' => $icao24]);
            $flight->callsign = $callsign;
            $flight->origin_country = $origin_country;
            $flight->last_contact = $last_contact;
            $flight->last_latitude = $latitude;
            $flight->last_longitude = $longitude;
            $flight->last_altitude = $altitude;

            if (is_null($flight->departure_time)) {
                if ($flight->last_on_ground === true && $on_ground === false && $velocity > 50) {
                    $flight->departure_time = $last_contact;
                    $flight->departure_latitude = $latitude;
                    $flight->departure_longitude = $longitude;
                    Log::info("Despegue detectado para $callsign a $last_contact");
                }
            }

            if (!is_null($flight->departure_time) && is_null($flight->arrival_time)) {
                if ($on_ground === true && $velocity < 10 && (time() - $last_contact) > 300) {
                    $flight->arrival_time = $last_contact;
                    $flight->arrival_latitude = $latitude;
                    $flight->arrival_longitude = $longitude;
                    Log::info("Aterrizaje detectado para $callsign a $last_contact");
                }
            }

            if ($callsign && (is_null($flight->departure_airport) || is_null($flight->arrival_airport))) {
                try {
                    $cleanCallsign = preg_replace('/\s+/', '', $callsign);
                    Log::debug("Limpieza de callsign: '$callsign' → '$cleanCallsign'");

                    $routeData = Cache::remember("hexdb_route_{$cleanCallsign}", 3600, function () use ($cleanCallsign) {
                        $url = "https://hexdb.io/api/v1/route/icao/{$cleanCallsign}";
                        Log::debug("Consultando ruta en HexDB: $url");
                        $response = Http::timeout(10)->get($url);
                        Log::debug("Respuesta de HexDB para $cleanCallsign: " . $response->body());
                        return $response->successful() ? $response->json() : null;
                    });

                    if ($routeData && isset($routeData['route']) && str_contains($routeData['route'], '-')) {
                        [$departureCode, $arrivalCode] = explode('-', $routeData['route'], 2);
                        Log::info("Ruta obtenida para $callsign: $departureCode -> $arrivalCode");

                        $flight->departure_airport = $flight->departure_airport ?? substr(trim($departureCode), 0, 4);
                        $flight->arrival_airport = $flight->arrival_airport ?? substr(trim($arrivalCode), 0, 4);

                        $getAirportData = function ($icaoCode) {
                            if (empty($icaoCode))
                                return null;
                            return Cache::remember("hexdb_airport_{$icaoCode}", 86400, function () use ($icaoCode) {
                                try {
                                    $url = "https://hexdb.io/api/v1/airport/icao/{$icaoCode}";
                                    Log::debug("Consultando datos de aeropuerto en HexDB: $url");
                                    $response = Http::timeout(8)->get($url);
                                    if ($response->successful()) {
                                        $data = $response->json();
                                        if (isset($data['latitude'], $data['longitude']) && is_numeric($data['latitude']) && is_numeric($data['longitude'])) {
                                            return $data;
                                        }
                                    }
                                    return null;
                                } catch (\Exception $e) {
                                    Log::warning("Error obteniendo aeropuerto $icaoCode: " . $e->getMessage());
                                    return null;
                                }
                            });
                        };

                        if (is_null($flight->departure_latitude) || is_null($flight->departure_longitude)) {
                            $departureData = $getAirportData($departureCode);
                            if ($departureData) {
                                $flight->departure_latitude = $departureData['latitude'];
                                $flight->departure_longitude = $departureData['longitude'];
                            } else {
                                Log::warning("No se encontraron coordenadas para el aeropuerto de salida $departureCode");
                            }
                        }

                        if (is_null($flight->arrival_latitude) || is_null($flight->arrival_longitude)) {
                            $arrivalData = $getAirportData($arrivalCode);
                            if ($arrivalData) {
                                $flight->arrival_latitude = $arrivalData['latitude'];
                                $flight->arrival_longitude = $arrivalData['longitude'];
                            } else {
                                Log::warning("No se encontraron coordenadas para el aeropuerto de llegada $arrivalCode");
                            }
                        }
                    } else {
                        Log::warning("No se pudo determinar ruta para $callsign. Datos devueltos: " . json_encode($routeData));
                    }
                } catch (\Exception $e) {
                    Log::error("Error procesando HexDB para $callsign: " . $e->getMessage());
                }
            }

            if (
                $flight->departure_latitude !== null && $flight->departure_longitude !== null &&
                $flight->arrival_latitude !== null && $flight->arrival_longitude !== null
            ) {
                $distanceKm = $this->haversine(
                    $flight->departure_latitude,
                    $flight->departure_longitude,
                    $flight->arrival_latitude,
                    $flight->arrival_longitude
                );

                $avgSpeedKps = 850 / 3600;
                $durationExpected = $distanceKm / $avgSpeedKps;
                $flight->duration_expected = $durationExpected;

                if (!is_null($flight->departure_time) && !is_null($flight->arrival_time)) {
                    $durationReal = $flight->arrival_time - $flight->departure_time;
                    $flight->duration_real = $durationReal;
                    $flight->delayed = $durationReal > ($durationExpected + 900);
                    Log::info("Vuelo $callsign duración real: $durationReal, esperada: $durationExpected");
                }
            } else {
                Log::info("No se pudo calcular duración para $callsign por falta de coordenadas | From: {$flight->departure_airport} To: {$flight->arrival_airport}");
            }

            $flight->last_on_ground = $on_ground;
            $flight->last_velocity = $velocity;

            if (
                $flight->departure_airport !== null &&
                $flight->arrival_airport !== null &&
                $flight->departure_latitude !== null &&
                $flight->departure_longitude !== null &&
                $flight->arrival_latitude !== null &&
                $flight->arrival_longitude !== null
            ) {
                if ($flight->isDirty()) {
                    $flight->save();
                    Log::info("Vuelo guardado: $callsign - con coordenadas completas");
                }
            } else {
                Log::info("Vuelo omitido por falta de coordenadas completas: $callsign");
            }

        }
    }
}
