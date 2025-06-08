<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\UpdateFlightsJob;

class RunUpdateFlightsJob extends Command
{
    protected $signature = 'flights:update';

    protected $description = 'Ejecuta manualmente el job UpdateFlightsJob';

    public function handle()
    {
        // Ejecutar el job sin queue para debug
        $job = new UpdateFlightsJob();
        $job->handle();

        $this->info('Job UpdateFlightsJob ejecutado correctamente.');
    }
}
