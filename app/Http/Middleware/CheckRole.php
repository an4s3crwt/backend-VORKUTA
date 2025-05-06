<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\Auth\Token\Exception\InvalidToken;

class CheckRole
{ public function handle(Request $request, Closure $next, $role)
    {
        $user = auth()->user();

        // Verificar el rol del usuario basado en el claim
        if (isset($user->customClaims['admin']) && $role == 'admin' && $user->customClaims['admin'] === true) {
            return $next($request);
        }

        if ($role == 'user' && !isset($user->customClaims['admin'])) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}
