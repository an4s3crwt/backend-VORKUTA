<?php

namespace App\Providers;

use App\Guards\FirebaseGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends \Illuminate\Foundation\Support\Providers\AuthServiceProvider
{
    public function boot()
    {
        $this->registerPolicies();

        Auth::extend('firebase', function ($app, $name, array $config) {
            return new FirebaseGuard(Auth::createUserProvider($config['provider']));
        });
    }
}
