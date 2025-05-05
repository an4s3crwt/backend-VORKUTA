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
    private $cachedKeys = null;

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            // Obtener la clave pública de Firebase basada en el kid
            $publicKey = $this->getFirebasePublicKey($token);

            // Validar el token con la clave pública
            $decodedToken = JWT::decode($token, $publicKey);

            // Asignar el usuario a la request para usarlo en los controladores
            $request->attributes->add(['firebase_user' => $decodedToken]);

        } catch (\Exception $e) {
            Log::error('Firebase auth error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage()
            ], 401);
        }

        return $next($request);
    }

    private function getFirebasePublicKey($token): Key
    {
        try {
            // Extraer el header del token manualmente para obtener el kid
            [$headerEncoded] = explode('.', $token);
            $headerJson = base64_decode(strtr($headerEncoded, '-_', '+/'));
            $header = json_decode($headerJson, true);

            if (!isset($header['kid'])) {
                throw new \Exception("KID not found in token header.");
            }

            $kid = $header['kid'];

            // Cacheamos las claves públicas de Firebase
            if ($this->cachedKeys === null) {
                $this->fetchAndCacheFirebasePublicKeys();
            }

            if (!isset($this->cachedKeys[$kid])) {
                throw new \Exception("Key with kid '$kid' not found in public keys.");
            }

            $publicKey = $this->cachedKeys[$kid];

            return new Key($publicKey, 'RS256');
        } catch (\Exception $e) {
            Log::error('Error while decoding JWT or fetching public keys: ' . $e->getMessage());
            throw $e;
        }
    }

    private function fetchAndCacheFirebasePublicKeys()
    {
        try {
            $client = new Client();
            $response = $client->get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
            $this->cachedKeys = json_decode($response->getBody(), true);

            Log::info('Firebase public keys fetched and cached successfully.');
        } catch (\Exception $e) {
            Log::error('Error while fetching Firebase public keys: ' . $e->getMessage());
            throw $e;
        }
    }
}
