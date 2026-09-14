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
            $credentialsPath = storage_path('credentials.json');

            if (!file_exists($credentialsPath)) {
                throw new \Exception("Firebase credentials file not found at: {$credentialsPath}");
            }

            $this->firebaseAuth = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->createAuth();

            return $this->firebaseAuth;
        });
    }

    public function boot()
    {
        //
    }
}