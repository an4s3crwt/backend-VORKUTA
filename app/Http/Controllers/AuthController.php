<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Exception\AuthException;

class AuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
        
         // Aplica autenticación solo a los métodos que la necesitan
    $this->middleware('firebase.auth')->except(['register']);  // Verifica que el usuario esté autenticado
        $this->middleware('check.user')->only(['login', 'me']); // Verifica que el usuario esté autenticado
        $this->middleware('check.admin')->only(['adminDashboard']); // Solo administradores pueden acceder a esto
    }

    /**
     * Handle user login with Firebase authentication
     */
    public function login(Request $request)
    {
        try {
            $decoded = $request->attributes->get('firebase_user');
            $uid = $decoded->sub;

            Log::info('Firebase UID received:', ['uid' => $uid]);

            $user = User::where('firebase_uid', $uid)->first();

            if (!$user) {
                return response()->json([
                    'error' => 'User not registered in backend',
                    'firebase_user' => $decoded
                ], 404);
            }

            // Actualizar datos del usuario desde Firebase
            $this->syncUserWithFirebase($user, $decoded);

            return response()->json([
                'message' => 'Login successful',
                'user' => $this->formatUserResponse($user),
                'is_admin' => $user->isAdmin(),
                'token_valid_until' => $decoded->exp,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Login error: '.$e->getMessage());
            return response()->json(['error' => 'Authentication failed'], 401);
        }
    }

    /**
     * Register a new user with Firebase data
     */
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

        try {
            // Verificar que el Firebase UID existe
            $firebaseUser = $this->firebaseAuth->getUser($request->firebase_uid);
            
            $user = User::create([
                'firebase_uid' => $request->firebase_uid,
                'email' => $request->email,
                'name' => $request->name,
                'password' => bcrypt(uniqid()), // Contraseña aleatoria, ya que Firebase maneja la autenticación
                'firebase_data' => [
                    'email_verified' => $firebaseUser->emailVerified,
                    'metadata' => [
                        'created_at' => $firebaseUser->metadata->createdAt,
                        'last_login_at' => $firebaseUser->metadata->lastLoginAt,
                    ],
                ],
            ]);

            return response()->json([
                'message' => 'User registered successfully',
                'user' => $this->formatUserResponse($user),
            ]);

        } catch (AuthException $e) {
            return response()->json(['error' => 'Invalid Firebase user'], 400);
        } catch (\Exception $e) {
            Log::error('Registration error: '.$e->getMessage());
            return response()->json(['error' => 'Registration failed'], 500);
        }
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        try {
            $decoded = $request->attributes->get('firebase_user');
            $user = User::where('firebase_uid', $decoded->sub)->firstOrFail();
            
            return response()->json([
                'user' => $this->formatUserResponse($user)
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    /**
     * Logout - Frontend should handle Firebase logout
     */
    public function logout(Request $request)
    {
        return response()->json(['message' => 'Logout successful']);
    }

    /**
     * Helper method to sync local user with Firebase data
     */
    protected function syncUserWithFirebase(User $user, $decodedToken)
    {
        try {
            $firebaseUser = $this->firebaseAuth->getUser($user->firebase_uid);
            
            $user->update([
                'last_activity' => now(),
                'firebase_data' => array_merge($user->firebase_data ?? [], [
                    'email_verified' => $firebaseUser->emailVerified,
                    'metadata' => [
                        'last_login_at' => $firebaseUser->metadata->lastLoginAt,
                    ],
                    'custom_claims' => $firebaseUser->customClaims ?? [],
                ]),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Firebase sync error: '.$e->getMessage());
        }
    }

    /**
     * Format user response consistently
     */
    protected function formatUserResponse(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'email_verified' => $user->firebase_data['email_verified'] ?? false,
            'last_activity' => $user->last_activity,
            'photo_url' => $user->photo_url,
        ];
    }

    /**
     * Example of admin-specific method (protected by the check.admin middleware)
     */
    public function adminDashboard(Request $request)
    {
        return response()->json([
            'message' => 'Welcome to the admin dashboard!',
            'user' => $request->get('firebase_user'),
        ]);
    }
}
