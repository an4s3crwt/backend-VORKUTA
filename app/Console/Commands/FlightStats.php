<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Flight;

class FlightStats extends Command
{
    protected $signature = 'flights:stats';
    protected $description = 'Muestra estadísticas básicas de vuelos almacenados';

    public function handle()
    {
        $total = Flight::count();
        $withTimes = Flight::whereNotNull('departure_time')->whereNotNull('arrival_time')->count();
        $delayed = Flight::where('delayed', true)->count();

        $this->info("Total de vuelos almacenados: $total");
        $this->info("Vuelos con tiempos de despegue y llegada registrados: $withTimes");
        $this->info("Vuelos marcados como retrasados: $delayed");
    }
}
