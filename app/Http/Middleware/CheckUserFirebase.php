<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserFirebase
{
    public function handle(Request $request, Closure $next)
    {
        // Verificar que el usuario esté presente en los atributos de la solicitud
        $user = $request->attributes->get('firebase_user');

        if (!$user) {
            return response()->json(['error' => 'Acceso denegado. Usuario no autenticado.'], 401);
        }

        return $next($request);
    }
}

