<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AirportStatsController extends Controller
{
    private $openskyUser;
    private $openskyPass;

    public function __construct()
    {
        $this->openskyUser = env('OPENSKY_USER', 'an4s3crwt');
        $this->openskyPass = env('OPENSKY_PASS', 'Mentaybolita1');
    }

    /**
     * show($icao)
     * Como tu React:
     *  - OpenSky → obtener flights
     *  - HexDB → callsign-route-iata
     *  - HexDB → airport/iata
     *  - devolver vuelos filtrados donde departure o arrival = ICAO/IATA destino
     */
    public function show($icao)
    {
        $icao = strtoupper($icao);

        // 1) Obtener estados de OpenSky
        $resp = Http::withBasicAuth($this->openskyUser, $this->openskyPass)
            ->get("https://opensky-network.org/api/states/all");

        if ($resp->failed()) {
            return response()->json([
                'success' => false,
                'error' => 'OpenSky error',
                'status' => $resp->status()
            ], 500);
        }

        $states = $resp->json('states') ?? [];

        // 2) Obtener lista de callsigns únicos
        $callsigns = collect($states)
            ->pluck(1)        // s[1] = callsign
            ->filter()
            ->unique()
            ->values()
            ->take(100)       // límite razonable
            ->all();

        $output = [];

        // 3) Por cada callsign → HexDB
        foreach ($callsigns as $cs) {

            $route = Http::get(
                "https://hexdb.io/callsign-route-iata?callsign=$cs"
            );

            if ($route->failed()) continue;

            $txt = trim($route->body());
            if (!str_contains($txt, '-')) continue;

            [$depIATA, $arrIATA] = array_map('trim', explode('-', $txt));

            if (!$depIATA) continue;

            // Detalles del aeropuerto de salida
            $depInfo = Http::get(
                "https://hexdb.io/api/v1/airport/iata/$depIATA"
            );
            if ($depInfo->failed()) continue;

            $dep = $depInfo->json();

            // Filtrar: solo devolver vuelos RELACIONADOS con el ICAO solicitado
            if (
                strtoupper($dep['icao'] ?? '') !== $icao &&
                strtoupper($arrIATA ?? '') !== $icao &&
                strtoupper($depIATA ?? '') !== $icao
            ) {
                continue;
            }

            $output[] = [
                'callsign' => $cs,
                'departure_iata' => $depIATA,
                'arrival_iata' => $arrIATA ?: '–',
                'airport_name' => $dep['airport'] ?? $depIATA,
                'country' => $dep['region_name'] ?? 'Unknown',
                'countryCode' => $dep['country_code'] ?? 'US',
            ];
        }

        return response()->json([
            'success' => true,
            'airport' => $icao,
            'flights' => array_values($output),
            'count' => count($output),
        ]);
    }
}
