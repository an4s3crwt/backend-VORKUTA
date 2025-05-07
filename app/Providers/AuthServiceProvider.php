<?php

namespace App\Providers;

use App\Guards\FirebaseGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use App\Providers\FirebaseAuthServiceProvider;

class AuthServiceProvider extends \Illuminate\Foundation\Support\Providers\AuthServiceProvider
{
   
public function boot()
{
    $this->registerPolicies();

    Auth::extend('firebase', function ($app, $name, array $config) {
        $provider = Auth::createUserProvider($config['provider']);
        return new FirebaseGuard($provider);
    });
}
}
