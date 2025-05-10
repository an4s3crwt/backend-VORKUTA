<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirebaseAuthMiddleware
{
    private const FIREBASE_KEY_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    private const CACHE_KEY = 'firebase_public_keys';
    private const CACHE_TTL = 3600; // 1 hora

    public function handle(Request $request, Closure $next)
{
    \Log::info('Inicio de middleware FirebaseAuth');
    
    // Obtener el encabezado de autorización
    $authHeader = $request->header('Authorization');
    \Log::debug('Encabezado Authorization:', ['header' => $authHeader]);

    // Obtener el token
    $token = $request->bearerToken();
    \Log::debug('Token extraído:', ['token' => $token ? 'presente' : 'ausente']);

    // Si no hay token, devolver un error
    if (!$token) {
        \Log::warning('Token no proporcionado');
        return response()->json(['error' => 'Token requerido'], 401);
    }

    try {
        \Log::debug('Intentando decodificar token...');
        $publicKey = $this->getFirebasePublicKey($token);
        
        // Aumentar leeway para pruebas
        JWT::$leeway = 3600; // 1 hora
        
        // Decodificar el token de Firebase
        $decodedToken = JWT::decode($token, $publicKey);
        
        \Log::info('Token decodificado correctamente', [
            'uid' => $decodedToken->sub ?? null,
            'issuer' => $decodedToken->iss ?? null,
            'issued_at' => $decodedToken->iat ?? null,
            'expiration' => $decodedToken->exp ?? null
        ]);

        // Agregar el token decodificado (claims) a la solicitud para su posterior uso
        $request->attributes->add(['firebase_user' => $decodedToken]);

        // Verificación del claim 'admin'
        // Se permite el acceso tanto a usuarios como administradores
        // Solo se bloquea el acceso si se requiere específicamente un admin
        if (isset($decodedToken->admin) && $decodedToken->admin === true) {
            return $next($request);  // Permitir el acceso si es un administrador
        }

        // Si el claim 'admin' no está presente o el usuario no es admin, se permite acceso a usuarios normales
        return $next($request);  // Continuar si es usuario normal

    } catch (\Exception $e) {
        \Log::error('Error en autenticación Firebase:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Error de autenticación',
            'message' => config('app.debug') ? $e->getMessage() : null,
            'timestamp' => time() // Para verificar sincronización de tiempos
        ], 401);
    }
}


    // Método para extraer el token del encabezado de la solicitud
    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }
        
        return $request->bearerToken(); // Fallback
    }

    // Método para obtener la clave pública de Firebase
    protected function getFirebasePublicKey(string $token): Key
    {
        $header = $this->extractTokenHeader($token);
        
        if (!isset($header['kid'])) {
            throw new \RuntimeException('KID not found in token header');
        }

        $keys = $this->fetchPublicKeys();
        $kid = $header['kid'];

        if (!isset($keys[$kid])) {
            throw new \RuntimeException("Public key for KID '$kid' not found");
        }

        return new Key($keys[$kid], 'RS256');
    }

    // Extraer el encabezado del token para obtener el 'kid'
    protected function extractTokenHeader(string $token): array
    {
        try {
            [$headerEncoded] = explode('.', $token);
            $headerJson = base64_decode(strtr($headerEncoded, '-_', '+/'));
            return json_decode($headerJson, true) ?: [];
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid token format', 0, $e);
        }
    }

    // Método para obtener las claves públicas de Firebase
    protected function fetchPublicKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $client = new Client(['timeout' => 5]);
                $response = $client->get(self::FIREBASE_KEY_URL);
                
                $keys = json_decode($response->getBody(), true);
                $cacheControl = $response->getHeaderLine('Cache-Control');
                
                Log::info('Fetched Firebase public keys', [
                    'key_count' => count($keys),
                    'cache_control' => $cacheControl
                ]);
                
                return $keys;
            } catch (\Exception $e) {
                Log::error('Failed to fetch Firebase public keys', ['error' => $e->getMessage()]);
                throw new \RuntimeException('Unable to retrieve Firebase public keys', 0, $e);
            }
        });
    }

    // Verificar que el token contiene los claims requeridos
    protected function validateToken(object $token): void
    {
        $requiredClaims = ['sub', 'iss', 'aud', 'iat', 'exp'];
        
        foreach ($requiredClaims as $claim) {
            if (!isset($token->{$claim})) {
                throw new \RuntimeException("Missing required claim: $claim");
            }
        }
        
        // Validar el issuer
        $projectId = config('firebase.project_id');
        if ($token->iss !== "https://securetoken.google.com/{$projectId}") {
            throw new \RuntimeException("Invalid token issuer");
        }
        
        // Validar el audience
        if ($token->aud !== $projectId) {
            throw new \RuntimeException("Invalid token audience");
        }
    }

    // Responder con error no autorizado
    protected function unauthorizedResponse(string $message, \Throwable $e = null): \Illuminate\Http\JsonResponse
    {
        $response = [
            'error' => 'Unauthorized',
            'message' => $message,
        ];
        
        if ($e && config('app.debug')) {
            $response['debug'] = $e->getMessage();
        }
        
        return response()->json($response, 401);
    }
}