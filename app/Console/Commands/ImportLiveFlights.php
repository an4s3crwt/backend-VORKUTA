<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Flight;
use App\Models\Airline;
use App\Models\Airport;

class ImportLiveFlights extends Command
{
    protected $signature = 'flights:import-live';
    protected $description = 'Importar vuelos en tiempo real desde OpenSky y HexDB';

   public function handle()
{
    $this->info('📡 Importando vuelos en vivo...');

    // Paso 1: Obtener datos desde OpenSky
    $response = Http::withBasicAuth(env('OPENSKY_USERNAME'), env('OPENSKY_PASSWORD'))
        ->get('https://opensky-network.org/api/states/all');

    if ($response->failed()) {
        $this->error('❌ Error al obtener datos de OpenSky.');
        Log::error('[flights:import-live] Falló OpenSky.');
        return;
    }

    // Limitar a los primeros 2500 vuelos
    $states = array_slice($response->json()['states'], 0, 2500);

    $this->info('✈️ Procesando hasta 2500 vuelos...');

    foreach ($states as $state) {
        $icao24 = $state[0];
        $callsign = trim($state[1]) ?: null;

        if (!$callsign) continue;

        // Paso 2: Obtener datos de HexDB por callsign
        $hexResponse = Http::get("https://hexdb.io/api/v1/callsign/{$callsign}");

        if ($hexResponse->ok()) {
            $hexData = $hexResponse->json();

            // Ignorar si falta el origen o destino
            if (empty($hexData['origin']['icao']) || empty($hexData['destination']['icao'])) {
                continue;
            }

            // Airline
            $airline = Airline::firstOrCreate(
                ['icao' => $hexData['airline']['icao'] ?? null],
                ['name' => $hexData['airline']['name'] ?? null]
            );

            // Aeropuertos
            $departureAirport = Airport::firstOrCreate(
                ['icao' => $hexData['origin']['icao']],
                ['name' => $hexData['origin']['name'] ?? null]
            );

            $arrivalAirport = Airport::firstOrCreate(
                ['icao' => $hexData['destination']['icao']],
                ['name' => $hexData['destination']['name'] ?? null]
            );

            // Guardar vuelo
            Flight::updateOrCreate(
                ['icao24' => $icao24],
                [
                    'callsign' => $callsign,
                    'airline_id' => $airline->id ?? null,
                    'departure_airport_id' => $departureAirport->id ?? null,
                    'arrival_airport_id' => $arrivalAirport->id ?? null,
                    'last_seen' => now(),
                    'status' => 'active',
                ]
            );
        }

        sleep(1); // Evitar saturar HexDB
    }

    $this->info('✅ Importación completada.');
}
}