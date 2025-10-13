<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Flight;
use App\Models\CompletedFlight;

class ProcessCompletedFlights extends Command
{
    protected $signature = 'flights:process-completed';
    protected $description = 'Procesa datos de vuelos y genera registros de vuelos completos';

    private const AVERAGE_SPEED_KPH = 850;

    private function haversine($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    public function handle()
    {
        $flightsGrouped = Flight::select('icao24', 'callsign', 'origin_country')
            ->distinct()
            ->get();

        foreach ($flightsGrouped as $flightInfo) {
            $flights = Flight::where('icao24', $flightInfo->icao24)
                ->where('callsign', $flightInfo->callsign)
                ->orderBy('last_contact')
                ->get();

            $departed = false;
            $departureTime = null;
            $arrivalTime = null;
            $departureLat = null;
            $departureLon = null;
            $arrivalLat = null;
            $arrivalLon = null;

            foreach ($flights as $f) {
                if (!$departed && !$f->last_on_ground && $f->last_velocity > 50) {
                    $departureTime = $f->last_contact;
                    $departed = true;
                    $departureLat = $f->last_latitude;
                    $departureLon = $f->last_longitude;
                }

                if ($departed && $f->last_on_ground && $f->last_velocity < 10) {
                    $arrivalTime = $f->last_contact;
                    $arrivalLat = $f->last_latitude;
                    $arrivalLon = $f->last_longitude;
                    break;
                }
            }

            if ($departureTime && $arrivalTime) {
                $durationReal = $arrivalTime - $departureTime;

                $distanceKm = $this->haversine(
                    $departureLat,
                    $departureLon,
                    $arrivalLat,
                    $arrivalLon
                );

                $avgSpeedKps = self::AVERAGE_SPEED_KPH / 3600;
                $durationExpected = $distanceKm / $avgSpeedKps;

                $delayed = $durationReal > ($durationExpected + 900); // 15 min margen

                CompletedFlight::updateOrCreate([
                    'icao24' => $flightInfo->icao24,
                    'callsign' => $flightInfo->callsign,
                ], [
                    'origin_country' => $flightInfo->origin_country,
                    'departure_time' => date('Y-m-d H:i:s', $departureTime),
                    'departure_latitude' => $departureLat,
                    'departure_longitude' => $departureLon,
                    'arrival_time' => date('Y-m-d H:i:s', $arrivalTime),
                    'arrival_latitude' => $arrivalLat,
                    'arrival_longitude' => $arrivalLon,
                    'duration_real' => $durationReal,
                    'duration_expected' => $durationExpected,
                    'delayed' => $delayed,
                ]);

                $this->info("Vuelo completo procesado: {$flightInfo->callsign}");
            }
        }

        $this->info('Procesamiento de vuelos completos finalizado.');
    }
}
