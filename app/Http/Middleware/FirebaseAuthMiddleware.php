<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirebaseAuthMiddleware
{

    //Cache for storing Firebase public keys to avoid repeated network requests
    private $cachedKeys = null;

    /**
     * HANLDE
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     */
    public function handle(Request $request, Closure $next)
    {
        //gets the token from the Auth header
        $token = $request->bearerToken();


        //Manage Unauthorized requests, if there is no token
        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            // Get the Firebase public key corresponding to the 'kid' (Key ID) in the JWT header (method below)
            $publicKey = $this->getFirebasePublicKey($token);

            // Decode and validate the JWT token using the public key
            $decodedToken = JWT::decode($token, $publicKey);

            // Asignar el usuario a la request para usarlo en los controladores
            $request->attributes->add(['firebase_user' => $decodedToken]);

        } catch (\Exception $e) {
            // Log the error cause i had some issues with token verification or decoding
            Log::error('Firebase auth error: ' . $e->getMessage());
            // Return Unauthorized (401) response with a fancy message
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage()
            ], 401);
        }
        // Pass the request to the next middleware/controller
        return $next($request);
    }

    /**
     * Summary of getFirebasePublicKey
     * @param mixed $token
     * @throws \Exception
     * @return Key
     */
    private function getFirebasePublicKey($token): Key
    {
        try {
            // Decode the JWT header to extract the 'kid' (Key ID)
            [$headerEncoded] = explode('.', $token);
            $headerJson = base64_decode(strtr($headerEncoded, '-_', '+/'));
            $header = json_decode($headerJson, true);

            // If the 'kid' is not found in the token's header, throw an exception
            if (!isset($header['kid'])) {
                throw new \Exception("KID not found in token header.");
            }

            //eXTRACT the KEY ID from the token's header
            $kid = $header['kid'];

            // Check if Firebase public keys are already cached; if not, fetch and cache them
            if ($this->cachedKeys === null) {
                $this->fetchAndCacheFirebasePublicKeys(); //(method below)
            }

            // If the 'kid' is not found in the cached keys, throw an exception
            if (!isset($this->cachedKeys[$kid])) {
                throw new \Exception("Key with kid '$kid' not found in public keys.");
            }

            // Retrieve the public key associated with the 'kid'
            $publicKey = $this->cachedKeys[$kid];

            // Return a Key object with the public key and the algorithm (RS256 used by Firebase JWTs)
            return new Key($publicKey, 'RS256');
        } catch (\Exception $e) {
            //log the error
            Log::error('Error while decoding JWT or fetching public keys: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Summary of fetchAndCacheFirebasePublicKeys
     * @return void
     */
    private function fetchAndCacheFirebasePublicKeys()
    {
        try {
            // Use Guzzle HTTP client to fetch the public keys from Firebase's endpoint
            $client = new Client();
            $response = $client->get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');

            // Cache the public keys in the class's cachedKeys variable for future use
            $this->cachedKeys = json_decode($response->getBody(), true);

            // Log a success message after successfully caching the keys
            Log::info('Firebase public keys fetched and cached successfully.');
        } catch (\Exception $e) {
            // If there is an error while fetching or caching the keys, log the error
            Log::error('Error while fetching Firebase public keys: ' . $e->getMessage());
            // Throw the exception to be handled by the 'handle' method
            throw $e;
        }
    }
}
