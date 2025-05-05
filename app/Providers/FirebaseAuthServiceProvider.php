<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Auth\ApiClient;

class FirebaseAuthServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(ApiClient::class, function ($app) {
            $config = config('firebase.projects.' . config('firebase.default'));
            $projectId = $config['project_id'];
            
            return new ApiClient($projectId, null, new \GuzzleHttp\Client(), new \Kreait\Firebase\Auth\SignIn\GuzzleHandler(new \GuzzleHttp\Client()), new \Kreait\Firebase\Util\DefaultClock());
        });
    }

    public function boot()
    {
        //
    }
}