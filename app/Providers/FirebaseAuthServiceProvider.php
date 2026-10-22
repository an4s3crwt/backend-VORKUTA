<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class FirebaseAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('firebase.auth', function ($app) {
            $factory = new Factory();

            // 1. Si existe la variable de entorno con el JSON en Render, úsala directamente
            if (env('FIREBASE_CREDENTIALS_JSON')) {
                $credentials = json_decode(env('FIREBASE_CREDENTIALS_JSON'), true);
                $factory = $factory->withServiceAccount($credentials);
            } 
            // 2. Si no, busca el archivo físico en storage/credentials.json
            elseif (file_exists(storage_path('credentials.json'))) {
                $factory = $factory->withServiceAccount(storage_path('credentials.json'));
            } 
            // 3. O en la carpeta de secretos de Render
            elseif (file_exists('/etc/secrets/credentials.json')) {
                $factory = $factory->withServiceAccount('/etc/secrets/credentials.json');
            } 
            else {
                throw new \Exception("No se encontraron credenciales de Firebase ni en variable de entorno ni en storage/credentials.json");
            }

            return $factory->createAuth();
        });
    }

    public function boot(): void
    {
        //
    }
}