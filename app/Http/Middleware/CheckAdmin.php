<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $decoded = $request->attributes->get('firebase_user');

        if (!$decoded || !isset($decoded->sub)) {
            return response()->json(['error' => 'Token inválido o usuario no autenticado'], 401);
        }

        $uid = $decoded->sub;

        try {
            $firebaseUser = $this->auth->getUser($uid);

            if (($firebaseUser->customClaims['admin'] ?? false) === true) {
                return $next($request);
            } else {
                return response()->json(['error' => 'Acceso denegado. No eres admin'], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al verificar claim: ' . $e->getMessage()], 500);
        }
    }
}
