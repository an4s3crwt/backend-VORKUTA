<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    // Login/registro con token de Firebase
    public function login(Request $request)
    { 
         return response()->json(['message' => 'Login successful']);
    }

    // Obtener usuario autenticado
    public function me(Request $request)
    {
         // Retorna los detalles del usuario autenticado usando el UID del token
         $uid = $request->firebaseUser;

         // Aquí puedes recuperar más detalles sobre el usuario desde tu base de datos
         $user = User::find($uid);  // O lo que necesites según tu base de datos
 
         return response()->json($user);
    }

    // Logout: no se necesita en backend con Firebase, ya que se gestiona desde el frontend
    public function logout(Request $request)
    {
        // Opcional: invalidar sesión si usas sesiones locales
        return response()->json(['message' => 'Sesión finalizada en frontend.']);
    }
}
