<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Flight;

class ExportCompletedFlights extends Command
{
    protected $signature = 'flights:export-completed {--path=storage/app/}';
    protected $description = 'Exportar vuelos completos (con llegada y salida) a CSV para ML';

    public function handle()
    {
        $path = $this->option('path');
        $filename = $path . 'completed_flights_' . date('Ymd_His') . '.csv';

        $flights = Flight::whereNotNull('departure_time')
                         ->whereNotNull('arrival_time')
                         ->get();

        if ($flights->isEmpty()) {
            $this->info('No hay vuelos completos para exportar.');
            return 0;
        }

        $handle = fopen($filename, 'w');

        // Cabeceras CSV
        fputcsv($handle, [
            'id', 'icao24', 'callsign', 'origin_country', 'departure_airport', 'arrival_airport',
            'departure_latitude', 'departure_longitude', 'arrival_latitude', 'arrival_longitude',
            'departure_time', 'arrival_time', 'duration_expected', 'duration_real', 'delayed'
        ]);

        foreach ($flights as $flight) {
            fputcsv($handle, [
                $flight->id,
                $flight->icao24,
                $flight->callsign,
                $flight->origin_country,
                $flight->departure_airport,
                $flight->arrival_airport,
                $flight->departure_latitude,
                $flight->departure_longitude,
                $flight->arrival_latitude,
                $flight->arrival_longitude,
                $flight->departure_time,
                $flight->arrival_time,
                $flight->duration_expected,
                $flight->duration_real,
                $flight->delayed ? 'yes' : 'no',
            ]);
        }

        fclose($handle);

        $this->info("Exportación completada: $filename");

        return 0;
    }
}
