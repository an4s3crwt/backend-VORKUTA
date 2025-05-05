<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
        $decoded = $request->attributes->get('firebase_user'); // 
        $uid = $decoded->sub; // Firebase UID viene en el claim "sub"
    
        Log::info('UID recibido en login:', ['uid' => $uid]);
    
        $user = User::where('firebase_uid', $uid)->first();
    
        if (!$user) {
            return response()->json(['error' => 'Usuario no registrado en el backend.'], 404);
        }
    
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
        ]);
    }
    

    // Registro de usuario con datos de Firebase
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firebase_uid' => 'required|string|unique:users,firebase_uid',
            'email' => 'required|string|email|unique:users',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $user = User::create([
            'firebase_uid' => $request->firebase_uid,
            'email' => $request->email,
            'name' => $request->name,
            'password' => bcrypt('firebase'), // valor dummy si no usas contraseña
        ]);


        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'user' => $user,
        ]);
    }

    // Obtener usuario autenticado
    public function me(Request $request)
    {
        // Retorna los detalles del usuario autenticado usando el UID del token
        $uid = $request->firebaseUser;

        // Aquí puedes recuperar más detalles sobre el usuario desde tu base de datos
        $user = User::where('firebase_uid', $uid)->first();

        return response()->json($user);
    }

    // Logout: no se necesita en backend con Firebase, ya que se gestiona desde el frontend
    public function logout(Request $request)
    {
        return response()->json(['message' => 'Sesión finalizada en frontend.']);
    }

}
