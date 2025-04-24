<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;
use App\Models\OpenSkyAircraft;
class FlightDataController extends Controller
{
 
    public function fetchOpenSky()
    {
        $maxFlights = 2500;
        $storedCount = 0;
        $allStates = [];
    
        while ($storedCount < $maxFlights) {
            $response = Http::get('https://opensky-network.org/api/states/all');
    
            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch OpenSky data'], 500);
            }
    
            $data = $response->json();
            $states = $data['states'] ?? [];
    
            // Prevent duplicates and overflow
            foreach ($states as $state) {
                if ($storedCount >= $maxFlights) break;
    
                // Prevent re-storing duplicates in current request
                if (in_array($state[0], array_column($allStates, 0))) continue;
    
                $allStates[] = $state;
                $storedCount++;
            }
    
            sleep(1); // Avoid hammering the API
        }
    
        // Now store in DB
        foreach ($allStates as $state) {
            OpenSkyAircraft::create([
                'icao24' => $state[0],
                'callsign' => $state[1],
                'origin_country' => $state[2],
                'time_position' => $state[3] ? now()->timestamp($state[3]) : null,
                'longitude' => $state[5],
                'latitude' => $state[6],
                'baro_altitude' => $state[7],
                'velocity' => $state[9],
            ]);
        }
    
        // Optionally fetch limited HexDB data
        $hexes = collect($allStates)->pluck(0)->filter()->take(50); // Just enrich 50 aircraft
        $hexdbData = [];
    
        foreach ($hexes as $hex) {
            $hexResp = Http::get("https://hexdb.io/api/v1/aircraft/$hex");
            if ($hexResp->ok()) {
                $hexdbData[$hex] = $hexResp->json();
            }
        }
    
        return response()->json([
            'stored' => $storedCount,
            'hexdb_enrichment' => $hexdbData,
        ]);
    }
}
