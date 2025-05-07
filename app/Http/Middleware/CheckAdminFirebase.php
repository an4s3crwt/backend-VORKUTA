<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;

class CheckAdminFirebase
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->get('firebase_user');

        if (isset($user->admin) && $user->admin === true) {
            return $next($request);
        }

        return response()->json(['error' => 'Acceso denegado. Solo administradores.'], 403);
    }
}
