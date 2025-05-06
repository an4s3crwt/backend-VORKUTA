<?php

namespace App\Guards;

use Kreait\Firebase\Auth\Token\Exception\InvalidToken;//exceeption thrown if Firebase token isn't valid
//interfaces for Guard 
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;

//to create an instance of Firebase's authenticator
use Kreait\Firebase\Factory;


/**
 * The FirebaseGuard is responsible for verifying that token and authenticating the user on the Laravel backend. 
 * 
 * This allows 
 * the backend to know which user is making a request,
 *  without the need for storing or handling passwords on the server.
 * 
 *. It intercepts requests with Firebase JWT tokens, verifies them, and retrieves the associated user from the database.
 */
class FirebaseGuard implements Guard
{
    protected $provider; // The user provider for Laravel (auth.php/providers/firebase_user = 'eloquent')
    protected $user; // the authenticated user (if any)

    public function __construct(UserProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * 1. Checks if the user is already authenticated and returns it
     * 2. Gets the Firebase JWT token from the Auth header (Bearer token)
     * 3. If no token is provided it returns null
     * 4. Uses Factory to create an instance of Firebase's authentication
     * 5. Verifies the received token and extracts the uid (firebase_uid on db and frontend) , the sub from the claims
     * 6. Calls $this->provider->retrivebyid($uid) to retrieve the user from the database on Laravel, so mysql
     * 
     */
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
        } catch (InvalidToken $e) { //invalid token returns null
            return null;
        }

        return $this->user; //else returns the authenticated user
    }

    /**
     * 
     * VALIDATE
     *Validates the credentials. Since Firebase is used, no traditional credentials are checked,,, it just verifies user and returns it(i don't think i use this method)
     */
    public function validate(array $credentials = [])
    {
        return $this->user() ? true : false;
    }

    /**
     * CHECK
     * Retrurns true if there is an authenticated user 
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * GUEST
     * for guest users , so it returns true if ther is NO authenticated user
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * HASUSER
     * Checks if a user is already stored in $user property (i don't use this method)
     */
    public function hasUser(): bool
    {
        return !is_null($this->user);
    }

    /**
     * ID
     * Returns the ID of the authenticated user or nulll
     */
    public function id()
    {
        return $this->user() ? $this->user()->getAuthIdentifier() : null;
    }


    /**
     * SETUSER
     * To manually set an authenticated user
     */
    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }

    private function isAdmin($user)
    {
        try {
            $factory = (new Factory())
                ->withServiceAccount(config('firebase.projects.app.credentials'))
                ->withProjectId(env('FIREBASE_PROJECT_ID'));

            $auth = $factory->createAuth();
            $userClaims = $auth->getUser($user->firebase_uid); // firebase_uid en la base de datos

            // Verificar si el usuario tiene el claim 'admin'
            return isset($userClaims->customClaims['admin']) && $userClaims->customClaims['admin'] === true;
        } catch (\Exception $e) {
            return false; // Si hay un error, se considera que no es admin
        }
    }
}
