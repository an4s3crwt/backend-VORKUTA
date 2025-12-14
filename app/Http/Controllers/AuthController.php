<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Exception\AuthException;
use Throwable;

class AuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
        
        // --- CAMBIO CLAVE PARA QUE NO DE NETWORK ERROR ---
        // Excluimos 'login' del middleware para verificar el token manualmente dentro
        // Así evitamos que Apache/Render bloqueen la petición antes de entrar.
        $this->middleware('firebase.auth')->except(['register', 'login']);  
        
        $this->middleware('check.user')->only(['me']); 
        // $this->middleware('check.admin')->only(['adminDashboard']); // Descomenta cuando tengas el dashboard
    }

    /**
     * LOGIN PROFESIONAL (Con verificación manual para evitar CORS/Middleware issues)
     */
    public function login(Request $request)
    {
        try {
            // 1. OBTENER EL TOKEN (Manual, sin middleware)
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json(['error' => 'Token no proporcionado'], 401);
            }

            // 2. VERIFICAR CON FIREBASE (Aquí usamos la librería real)
            try {
                $verifiedIdToken = $this->firebaseAuth->verifyIdToken($token);
                $uid = $verifiedIdToken->claims()->get('sub');
                
                // Obtenemos los datos del token para actualizar
                $firebaseUser = $this->firebaseAuth->getUser($uid);
                
            } catch (Throwable $e) {
                return response()->json(['error' => 'Token inválido o expirado: ' . $e->getMessage()], 401);
            }

            Log::info('Firebase UID received and verified:', ['uid' => $uid]);

            // 3. BUSCAR O CREAR USUARIO (Sincronización Automática)
            // Si el usuario existe en Firebase pero no en SQL (porque borramos la tabla),
            // lo creamos al vuelo para que no de error 404.
            $user = User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'email' => $firebaseUser->email,
                    'name' => $firebaseUser->displayName ?? explode('@', $firebaseUser->email)[0],
                    'password' => bcrypt($uid), // Password dummy
                    'firebase_data' => [
                         'email_verified' => $firebaseUser->emailVerified,
                         'metadata' => [
                             'last_login_at' => $firebaseUser->metadata->lastLoginAt,
                         ]
                    ]
                ]
            );

            // 4. GENERAR TOKEN SANCTUM (Para que Laravel te deje seguir navegando)
            // Borramos tokens viejos para no acumular basura
            $user->tokens()->delete();
            $sanctumToken = $user->createToken('auth_token')->plainTextToken;

            // =========================================================
            // AUDITORÍA (LOGS)
            // =========================================================
            try {
                // Verificamos si la tabla logs existe antes de insertar (por si acaso)
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
            // =========================================================

            return response()->json([
                'message' => 'Login successful',
                'user' => $this->formatUserResponse($user),
                'access_token' => $sanctumToken, // IMPORTANTE para el Frontend
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
        $validator = Validator::make($request->all(), [
            'firebase_uid' => 'required|string', // Quitamos unique estricto por si acaso
            'email' => 'required|string|email',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            // Verificar que el usuario existe en Firebase real
            $firebaseUser = $this->firebaseAuth->getUser($request->firebase_uid);
            
            // Usamos firstOrCreate para evitar duplicados si le das dos veces al botón
            $user = User::firstOrCreate(
                ['firebase_uid' => $request->firebase_uid],
                [
                    'email' => $request->email,
                    'name' => $request->name,
                    'password' => bcrypt(uniqid()), 
                    'firebase_data' => [
                        'email_verified' => $firebaseUser->emailVerified,
                        'metadata' => [
                            'created_at' => $firebaseUser->metadata->createdAt,
                            'last_login_at' => $firebaseUser->metadata->lastLoginAt,
                        ],
                    ],
                ]
            );

            // Generar token
            $sanctumToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $this->formatUserResponse($user),
                'access_token' => $sanctumToken,
            ]);

        } catch (\Exception $e) {
            Log::error('Registration error: '.$e->getMessage());
            return response()->json(['error' => 'Registration failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->tokens()->delete();
        }
        return response()->json(['message' => 'Logout successful']);
    }

    // Helper para formatear
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

    /**
     * Get current authenticated user details for Profile Page
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
             return response()->json(['error' => 'No autorizado'], 401);
        }

        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'avatar_url'    => $user->photo_url, 
            'role'          => $user->role,
            'is_admin'      => $user->role === 'admin',
            'created_at'    => $user->created_at,
        ]);
    }
}