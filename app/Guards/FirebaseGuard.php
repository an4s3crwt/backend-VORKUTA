<?php

namespace App\Guards;

use Illuminate\Contracts\Auth\Authenticatable;
use Kreait\Firebase\Auth\Token\Exception\InvalidToken;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Kreait\Firebase\Factory;

class FirebaseGuard implements Guard
{
    protected $provider;
    protected $user;

    public function __construct(UserProvider $provider)
    {
        $this->provider = $provider;
    }

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }
    
        $token = request()->bearerToken();
        if (!$token) {
            return null;
        }
    
        try {
            $factory = (new Factory())
                ->withServiceAccount(config('firebase.projects.app.credentials'))
                ->withProjectId(env('FIREBASE_PROJECT_ID'));
            $auth = $factory->createAuth();
            $verifiedIdToken = $auth->verifyIdToken($token);
    
            // Obtener los claims del token
            $claims = $verifiedIdToken->claims();
            $uid = $claims->get('sub');  // UID del usuario
    
            // Verificar si el usuario es admin, si es necesario
            $isAdmin = $claims->get('admin', false);
            if (!$isAdmin) {
                // En vez de devolver un JsonResponse, retorna null
                return null;
            }
    
            $this->user = $this->provider->retrieveById($uid);
        } catch (InvalidToken $e) {
            return null;
        }
    
        return $this->user;
    }

  


    
    public function validate(array $credentials = [])
    {
        return $this->user() ? true : false;
    }

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function hasUser(): bool
    {
        return !is_null($this->user);
    }

    public function id()
    {
        return $this->user() ? $this->user()->getAuthIdentifier() : null;
    }

    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }

}