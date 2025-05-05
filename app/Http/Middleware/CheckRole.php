<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        // Obtén el usuario autenticado
        $user = auth()->user();

        // Verifica si el usuario tiene el rol adecuado
        if ($user && $user->role !== $role) {
            return response()->json(['message' => 'No tienes permisos para acceder a este recurso'], 403);
        }

        // Si el rol es válido, continúa con la solicitud
        return $next($request);
    }
}
