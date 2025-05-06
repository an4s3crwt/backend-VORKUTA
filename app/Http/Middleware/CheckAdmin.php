<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Kreait\Firebase\Auth as FirebaseAuth;

class CheckAdmin
{


    protected $auth;

    public function __construct(FirebaseAuth $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next)
    {
        // Obtener el UID del usuario autenticado
        $user = auth()->user();
        $uid = $user->firebase_uid; // Asegúrate de que el UID está disponible

        // Verificar los claims del usuario en Firebase
        try {
            $firebaseUser = $this->auth->getUser($uid);
            if (isset($firebaseUser->customClaims['admin']) && $firebaseUser->customClaims['admin'] === true) {
                return $next($request);
            } else {
                return response()->json(['error' => 'Acceso denegado. No eres un administrador.'], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al verificar el claim de admin: ' . $e->getMessage()], 500);
        }
    }
}
