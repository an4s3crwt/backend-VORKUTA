<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Injecting the Firebase Auth service into the controller
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    /**
     * Summary of login
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
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
    $firebaseUser = $request->attributes->get('firebase_user'); // Get the verified Firebase user

    $uid = $firebaseUser->sub; // Extract UID from Firebase token claims

    $user = User::where('firebase_uid', $uid)->first();

    if (!$user) {
        return response()->json(['error' => 'User not found.'], 404);
    }

    return response()->json([
        'name' => $user->name,
        'email' => $user->email,
    ]);
}


    // Logout: no se necesita en backend con Firebase, ya que se gestiona desde el frontend
    public function logout(Request $request)
    {
        return response()->json(['message' => 'Sesión finalizada en frontend.']);
    }

    public function getUserUid(){
        try{
            $user = $this->getUserByEmail(auth()->user()->email);
            return $user->uid;
        }catch(\Exception $e){
            return response()->json(['error' => 'No se pudo obtener el uid'. $e->getMessage()], 500);
        }
    }

}
