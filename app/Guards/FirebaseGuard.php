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
            $claims = $verifiedIdToken->claims(); // Cambiado: usar claims() en lugar de getClaim()
            $uid = $claims->get('sub'); // 'sub' es el claim que contiene el UID del usuario
    
            // Verificar que el token no fue emitido en el futuro
            $currentTimestamp = time(); // Obtiene el timestamp actual en el servidor
            $issuedAt = $claims->get('iat'); // Timestamp de cuando se emitió el token
    
            // Convertir el issuedAt de DateTimeImmutable a timestamp
            $issuedAtTimestamp = $issuedAt instanceof \DateTimeImmutable ? $issuedAt->getTimestamp() : $issuedAt;
    
            // Leeway para permitir un margen de error de tiempo
            $leeway = 300; // 5 minutes instead of 10 seconds
    
            // Si el token fue emitido más allá del tiempo actual + el margen de error, lo consideramos inválido
            if ($issuedAtTimestamp > ($currentTimestamp + $leeway)) {
                return null; // O maneja el error de la forma que prefieras
            }
    
            // Recupera el usuario desde el proveedor con el uid
            $this->user = $this->provider->retrieveById($uid);
        } catch (InvalidToken $e) { // Si el token es inválido, retorna null
            return null;
        }
    
        return $this->user; // Retorna el usuario autenticado
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
