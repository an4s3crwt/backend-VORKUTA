<?php

namespace App\Providers;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\AuthException;
use Illuminate\Support\ServiceProvider;

class FirebaseAuthServiceProvider extends ServiceProvider
{
    protected $firebaseAuth;

    public function register()
    {
        $this->app->bind(Auth::class, function ($app) {
            $config = config('firebase.projects.' . config('firebase.default'));
            
            if (!isset($config['credentials'])) {
                throw new \Exception('Firebase credentials not defined.');
            }

            // Configuración de la autenticación de Firebase
            $this->firebaseAuth = (new Factory)
                ->withServiceAccount($config['credentials'])
                ->createAuth();

            return $this->firebaseAuth;
        });
    }

    

    public function boot()
    {
        //
    }
}
