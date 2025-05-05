<?php

namespace App\Providers;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Illuminate\Support\ServiceProvider;

class FirebaseAuthServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(Auth::class, function ($app) {
            $config = config('firebase.projects.' . config('firebase.default'));
            
            if (!isset($config['credentials'])) {
                throw new \Exception('Firebase credentials not defined.');
            }

            return (new Factory)
                ->withServiceAccount($config['credentials'])
                ->createAuth();
        });
    }
    

    public function boot()
    {
        //
    }
}
