<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kreait\Firebase\Exception\AuthException;
use Throwable;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('firebase.auth')->except(['register', 'login']);  
        $this->middleware('check.user')->only(['me']); 
    }

    /**
     * LOGIN PROFESIONAL
     */
    public function login(Request $request)
    {
        Log::info('LOGIN ATTEMPT - Headers:', ['auth' => $request->header('Authorization')]);
        Log::info('LOGIN ATTEMPT - Bearer Token:', ['token' => $request->bearerToken()]);

        try {
            // Obtenemos la instancia configurada en FirebaseAuthServiceProvider
            $firebaseAuth = app('firebase.auth');

            // 1. OBTENER EL TOKEN
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json(['error' => 'Token no proporcionado'], 401);
            }

            // 2. VERIFICAR CON FIREBASE
            try {
                $verifiedIdToken = $firebaseAuth->verifyIdToken($token, false, 600);
                $uid = $verifiedIdToken->claims()->get('sub');
                $firebaseUser = $firebaseAuth->getUser($uid);
            } catch (Throwable $e) {
                Log::error('Error validando token en login: ' . $e->getMessage());
                return response()->json(['error' => 'Token inválido o expirado: ' . $e->getMessage()], 401);
            }

            Log::info('Firebase UID received and verified:', ['uid' => $uid]);

            // 3. BUSCAR O CREAR USUARIO (Sincronización Automática con Postgres)
            $user = User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'email' => $firebaseUser->email,
                    'name' => $firebaseUser->displayName ?? explode('@', $firebaseUser->email)[0],
                    'password' => bcrypt($uid),
                    'firebase_data' => [
                         'email_verified' => $firebaseUser->emailVerified,
                         'metadata' => [
                             'last_login_at' => $firebaseUser->metadata->lastLoginAt,
                         ]
                    ]
                ]
            );

            // 4. GENERAR TOKEN SANCTUM
            $user->tokens()->delete();
            $sanctumToken = $user->createToken('auth_token')->plainTextToken;

            // =========================================================
            // AUDITORÍA (LOGS)
            // =========================================================
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('logs')) {
                    DB::table('logs')->insert([
                        'user_id'    => $user->id,
                        'action'     => 'login',
                        'details'    => 'Login exitoso desde Render',
                        'ip_address' => $request->ip(),
                        'level'      => 'info',
                        'created_at' => now(),
                    ]);
                }
            } catch (\Exception $logEx) {
                Log::error("Error log auditoría: " . $logEx->getMessage());
            }

            return response()->json([
                'message' => 'Login successful',
                'user' => $this->formatUserResponse($user),
                'access_token' => $sanctumToken,
                'token_type' => 'Bearer',
                'is_admin' => $user->isAdmin(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Login Critical Error: '.$e->getMessage());
            return response()->json(['error' => 'Authentication failed', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Register a new user with Firebase data
     */
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            'firebase_uid' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make(Str::random(24)),
                'firebase_uid' => $request->firebase_uid,
            ]);
        } else {
            $user->update([
                'firebase_uid' => $request->firebase_uid,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }
        return response()->json(['message' => 'Logout successful']);
    }

    protected function formatUserResponse(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'photo_url' => $user->photo_url,
        ];
    }

    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
             return response()->json(['error' => 'No autorizado'], 401);
        }

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'avatar_url' => $user->photo_url, 
            'role'       => $user->role,
            'is_admin'   => $user->role === 'admin',
            'created_at' => $user->created_at,
        ]);
    }
}