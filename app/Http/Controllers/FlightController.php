<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FlightController extends Controller
{
    // Función 1: Para ver el detalle de UN vuelo (Ya la tenías)
    public function getFlightData($icao)
    {
        $url = 'https://opensky-network.org/api/states/all';

        $response = Http::withBasicAuth(
            env('OPENSKY_USERNAME'), 
            env('OPENSKY_PASSWORD')
        )->get($url, [
            'icao24' => $icao 
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Error OpenSky'], $response->status());
        }

        return response()->json($response->json());
    }

    // 👇 FUNCIÓN NUEVA: Para ver TODOS los vuelos (Para AirportsData.jsx)
    public function getAllFlights()
    {
        $url = 'https://opensky-network.org/api/states/all';

        // Hacemos la petición SIN el parámetro icao24 para obtener todo
        $response = Http::withBasicAuth(
            env('OPENSKY_USERNAME'), 
            env('OPENSKY_PASSWORD')
        )->get($url);

        if ($response->failed()) {
            return response()->json([
                'error' => 'No se pudo conectar con OpenSky para obtener la lista',
                'details' => $response->body()
            ], $response->status());
        }

        return response()->json($response->json());
    }

    // 👇 NUEVA FUNCIÓN: Obtener vuelos por zona geográfica
    public function getFlightsByArea(Request $request)
    {
        // Validamos que lleguen las coordenadas
        $request->validate([
            'lamin' => 'required',
            'lomin' => 'required',
            'lamax' => 'required',
            'lomax' => 'required',
        ]);

        $url = 'https://opensky-network.org/api/states/all';

        // OpenSky acepta estos parámetros para filtrar geográficamente
        $response = Http::withBasicAuth(
            env('OPENSKY_USERNAME'), 
            env('OPENSKY_PASSWORD')
        )->get($url, [
            'lamin' => $request->lamin,
            'lomin' => $request->lomin,
            'lamax' => $request->lamax,
            'lomax' => $request->lomax,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'OpenSky Error'], $response->status());
        }

        return response()->json($response->json());
    }


    // 👇 NUEVA FUNCIÓN: Vuelos cercanos (Radio)
    public function getNearbyFlights(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
         'radius' => 'numeric|max:2000',
        ]);

        $lat = $request->lat;
        $lon = $request->lon;
        $radiusKm = $request->radius ?? 100; // Por defecto 100km

        // 1. Convertimos km a grados (aprox)
        // 1 grado latitud ~= 111 km
        $deltaLat = $radiusKm / 111;
        $deltaLon = $radiusKm / (111 * cos(deg2rad($lat)));

        // 2. Calculamos la caja (Bounding Box)
        $lamin = $lat - $deltaLat;
        $lamax = $lat + $deltaLat;
        $lomin = $lon - $deltaLon;
        $lomax = $lon + $deltaLon;

        // 3. Pedimos a OpenSky
        $url = 'https://opensky-network.org/api/states/all';
        $response = Http::withBasicAuth(env('OPENSKY_USERNAME'), env('OPENSKY_PASSWORD'))
                        ->get($url, [
                            'lamin' => $lamin, 'lomin' => $lomin, 
                            'lamax' => $lamax, 'lomax' => $lomax
                        ]);

        if ($response->failed()) return response()->json(['error' => 'Error OpenSky'], 500);
        
        $data = $response->json();
        $states = $data['states'] ?? [];
        $nearby = [];

        // 4. Filtramos y calculamos distancia real (Haversine simplificado)
        foreach ($states as $s) {
            $fLat = $s[6];
            $fLon = $s[5];
            if (!$fLat || !$fLon) continue;

            // Distancia Euclídea rápida (para filtrar fino)
            $d = sqrt(pow($fLat - $lat, 2) + pow($fLon - $lon, 2)) * 111;
            
            if ($d <= $radiusKm) {
                // Añadimos la distancia al final del array del avión
                $s[] = round($d, 2); 
                $nearby[] = $s;
            }
        }

        // Ordenar por cercanía
        usort($nearby, fn($a, $b) => end($a) <=> end($b));

        return response()->json(['nearby_flights' => $nearby]);
    }
}