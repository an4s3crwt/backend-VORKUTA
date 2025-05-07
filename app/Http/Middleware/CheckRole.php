<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth as FirebaseAuth;
use Firebase\Auth\Token\Exception\InvalidToken;

class CheckRole
{
    protected $auth;

    // Inyectar el servicio FirebaseAuth
    public function __construct(FirebaseAuth $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, Closure $next, $role)
    {
        try {
            // Obtener el token de Firebase desde el header Authorization
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json(['error' => 'Token no proporcionado'], 401);
            }

            // Verificar el token con Firebase
            $verifiedIdToken = $this->auth->verifyIdToken($token);
            $firebaseUser = $this->auth->getUser($verifiedIdToken->getClaim('sub'));

            // Verifica si el usuario tiene los claims necesarios
            if ($role == 'admin') {
                // Verificar si el claim 'admin' es verdadero
                if (isset($firebaseUser->customClaims['admin']) && $firebaseUser->customClaims['admin'] === true) {
                    return $next($request);
                }
                return response()->json(['error' => 'Acceso no autorizado, rol de administrador requerido'], 403);
            }

            // Si es un usuario común, verificar que no tenga el claim 'admin'
            if ($role == 'user' && !isset($firebaseUser->customClaims['admin'])) {
                return $next($request);
            }

            return response()->json(['error' => 'Acceso no autorizado, rol no permitido'], 403);

        } catch (InvalidToken $e) {
            // Log el error de token no válido
            Log::error('Token inválido: ' . $e->getMessage());
            return response()->json(['error' => 'Token inválido o no verificable'], 401);
        } catch (\Exception $e) {
            // Log el error genérico
            Log::error('Error al verificar usuario en CheckRole: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno en la autenticación'], 500);
        }
    }
}
