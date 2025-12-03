<?php

namespace App\Guards;

use Illuminate\Contracts\Auth\Authenticatable;
use Kreait\Firebase\Auth\Token\Exception\InvalidToken;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken; // Importante añadir esto
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

            // --- CORRECCIÓN AQUÍ ---
            // El segundo parámetro (120) es el "leeway" en segundos.
            // Esto permite que el token sea válido aunque tu servidor tenga
            // hasta 2 minutos de retraso respecto a Google.
            $verifiedIdToken = $auth->verifyIdToken($token, 120);
    
            // Obtener los claims del token
            $claims = $verifiedIdToken->claims();
            $uid = $claims->get('sub');  // UID del usuario
    
            // Verificar si el usuario es admin, si es necesario
            $isAdmin = $claims->get('admin', false);
            if (!$isAdmin) {
                return null;
            }
    
            $this->user = $this->provider->retrieveById($uid);
            
        } catch (InvalidToken $e) {
            // Token inválido (formato mal, expirado, etc)
            return null;
        } catch (FailedToVerifyToken $e) {
            // Error específico de verificación (como el del futuro)
            // Lo capturamos para que no lance error 500, sino 401 (null)
            return null;
        } catch (\Throwable $e) {
            // Captura general por seguridad
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