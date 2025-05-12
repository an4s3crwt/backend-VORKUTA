<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OpenSkyAircraft;
use App\Http\Controllers\OpenSkyController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use App\Models\FlightPrediction;

use Tymon\JWTAuth\Facades\JWTAuth;

class FlightDataController extends Controller
{
    public function store(Request $request)
    {
        \Log::info('Solicitud recibida en:', [now()]);
        \Log::info('Headers:', $request->headers->all());
        \Log::info('Datos recibidos:', $request->all());

        $states = $request->input('states', []);
        \Log::info('Número de estados recibidos:', ['count' => count($states)]);

        if (empty($states)) {
            \Log::warning('Array de estados vacío');
            return response()->json([
                'success' => false,
                'error' => 'No se recibieron datos de vuelos'
            ], 400);
        }

        $flights = [];
        foreach ($states as $index => $state) {
            if (!is_array($state)) {
                \Log::warning('Estado no es array en índice:', ['index' => $index, 'state' => $state]);
                continue;
            }

            // Skip flights without a valid callsign
            $callsign = $state[1] ?? null;
            if (!$callsign || $callsign === 'N/A' || $callsign === '') {
                continue;
            }

            $flights[] = [
                'icao' => $state[0] ?? 'N/A',
                'callsign' => $callsign,
                'latitude' => $state[6] ?? 0,
                'longitude' => $state[5] ?? 0,
                'altitude' => $state[7] ?? 0,
                'speed' => $state[9] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \Log::info('Datos preparados para inserción:', ['count' => count($flights)]);

        try {
            if (empty($flights)) {
                \Log::info('No hay vuelos válidos con callsign para almacenar');
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron vuelos con callsign válido',
                    'inserted' => 0
                ]);
            }

            $result = OpenSkyAircraft::insert($flights);
            \Log::info('Resultado de inserción:', ['result' => $result]);

            return response()->json([
                'success' => true,
                'message' => 'Datos almacenados correctamente',
                'inserted' => count($flights)
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en inserción:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error en base de datos',
                'details' => $e->getMessage()
            ], 500);
        }
    }







    public function getNearbyFlights(Request $request)
    {
        try {
            $user = $request->attributes->get('firebase_user');

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized', 'details' => $e->getMessage()], 401);
        }

        $lat = $request->query('lat');
        $lon = $request->query('lon');
        $radius = $request->query('radius', 100); // en kilómetros
        $fallbackCount = 5;

        if (!$lat || !$lon) {
            return response()->json(['error' => 'Missing lat/lon'], 400);
        }

        $openSkyController = new OpenSkyController();
        $liveData = $openSkyController->fetchLiveData();

        $flightsWithDistance = collect($liveData['states'])->map(function ($flight) use ($lat, $lon) {
            $flightLat = $flight[6];
            $flightLon = $flight[5];

            if (is_null($flightLat) || is_null($flightLon)) {
                return null;
            }

            $distance = $this->calculateDistance($lat, $lon, $flightLat, $flightLon);
            $flight[] = $distance;
            return $flight;
        })->filter();

        $nearby = $flightsWithDistance->filter(function ($f) use ($radius) {
            return $f[count($f) - 1] <= $radius;
        });

        $closest = $flightsWithDistance->sortBy(function ($f) {
            return $f[count($f) - 1];
        })->take($fallbackCount)->values();

        return response()->json([
            'nearby_flights' => $nearby->isNotEmpty() ? $nearby->values() : $closest,
            'note' => $nearby->isNotEmpty()
                ? "Showing flights within {$radius}km."
                : "No nearby flights within {$radius}km. Showing closest ones instead."
        ]);
    }

    //Distancia Haversine
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
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


    



}