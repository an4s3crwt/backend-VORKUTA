<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\FlightDataController;

class FetchOpenSkyFlights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-open-sky-flights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
{
    $controller = new FlightDataController();
    $result = $controller->fetchOpenSky();

    $this->info("OpenSky data fetched and stored.");
}
}
