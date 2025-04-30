<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OpenSkyAircraft;
use Illuminate\Support\Facades\Http;


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

            $flights[] = [
                'icao' => $state[0] ?? 'N/A',
                'callsign' => $state[1] ?? 'N/A',
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


    public function getAllData()
    {
        $data = OpenSkyAircraft::all();
        return response()->json($data);
    }

    private function fetchLiveDataFromOpenSky()
{
    $url = "https://opensky-network.org/api/states/all";

    try {
        $response = Http::timeout(10)->get($url);

        if ($response->successful()) {
            return $response->json();
        } else {
            \Log::error('Error al consultar OpenSky:', ['status' => $response->status()]);
            return ['states' => []];
        }
    } catch (\Exception $e) {
        \Log::error('Excepción al llamar OpenSky:', ['message' => $e->getMessage()]);
        return ['states' => []];
    }
}


    public function getNearbyFlights(Request $request)
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');
        $radius = $request->query('radius', 100); // km
        $fallbackCount = 5;

        if (!$lat || !$lon) {
            return response()->json(['error' => 'Missing lat/lon'], 400);
        }

        $liveData = $this->fetchLiveDataFromOpenSky();

        $flightsWithDistance = collect($liveData['states'])->map(function ($flight) use ($lat, $lon) {
            $flightLat = $flight[6];
            $flightLon = $flight[5];

            if (is_null($flightLat) || is_null($flightLon))
                return null;

            $distance = $this->calculateDistance($lat, $lon, $flightLat, $flightLon);
            $flight['distance'] = $distance;
            return $flight;
        })->filter(); // remove nulls

        // Vuelos dentro del radio
        $nearby = $flightsWithDistance->filter(fn($f) => $f['distance'] <= $radius);

        if ($nearby->isNotEmpty()) {
            return response()->json([
                'nearby_flights' => $nearby->values()
            ]);
        }

        // Si no hay vuelos cercanos, devuelve los más cercanos
        $closest = $flightsWithDistance->sortBy('distance')->take($fallbackCount)->values();

        return response()->json([
            'nearby_flights' => $closest,
            'note' => "No nearby flights within {$radius}km. Showing closest ones instead."
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