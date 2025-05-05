<?php

namespace App\Guards;

use Kreait\Firebase\Auth\Token\Exception\InvalidToken;
use Illuminate\Contracts\Auth\Authenticatable;
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
            // En la versión 7, para obtener las claims usa "claims()"
            $uid = $verifiedIdToken->claims()->get('sub');

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
