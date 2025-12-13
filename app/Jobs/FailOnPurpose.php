<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

class FailOnPurpose implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Esto lanzará una excepción y Laravel la guardará en 'failed_jobs'
        throw new Exception("¡Error simulado! La API de OpenSky no respondió a tiempo (TimeOut 30s).");
    }
}