<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CheckAdminFirebase
{
    public function handle(Request $request, Closure $next)
    {
        // Se espera que 'firebase_user' sea decodificado en un middleware anterior
        $decoded = $request->attributes->get('firebase_user');
    
        // Verificar si el usuario decodificado existe y si es un administrador
        if (!$decoded || !isset($decoded->admin) || $decoded->admin !== true) {
            // Si no es un admin, devolver respuesta de acceso denegado
            return response()->json(['error' => 'Acceso denegado: solo administradores'], 403);
        }
    
        // Si todo está bien, pasa la solicitud al siguiente middleware o controlador
        return $next($request);
    }
}
