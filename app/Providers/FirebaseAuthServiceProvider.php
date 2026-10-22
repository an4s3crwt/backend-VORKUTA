<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class FirebaseAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirebaseAuth::class, function ($app) {
            $factory = new Factory();

            // Cargar credenciales desde variable o archivo
            if (env('FIREBASE_CREDENTIALS_JSON')) {
                $credentials = json_decode(env('FIREBASE_CREDENTIALS_JSON'), true);
                $factory = $factory->withServiceAccount($credentials);
            } elseif (file_exists(storage_path('credentials.json'))) {
                $factory = $factory->withServiceAccount(storage_path('credentials.json'));
            }

            return $factory->createAuth();
        });

        // Mantener compatibilidad con alias 'firebase.auth'
        $this->app->alias(FirebaseAuth::class, 'firebase.auth');
    }

    public function boot(): void
    {
        //
    }
}