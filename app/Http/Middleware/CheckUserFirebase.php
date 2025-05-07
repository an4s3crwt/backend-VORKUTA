<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;

class CheckUserFirebase
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->get('firebase_user');

        if ($user) {
            return $next($request);
        }

        return response()->json(['error' => 'Acceso denegado. Usuario no autenticado.'], 401);
    }
}
