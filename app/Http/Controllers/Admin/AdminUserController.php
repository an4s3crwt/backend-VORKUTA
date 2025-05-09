<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    protected $auth;

    // Inyectamos el servicio de Firebase Auth
    public function __construct(FirebaseAuth $auth)
    {
        $this->auth = $auth;
    }

    public function index()
    {
        try {
            // Obtener todos los usuarios de Firebase (hasta 1000)
            $firebaseUsers = collect($this->auth->listUsers(1000));
    
            // Contar los usuarios totales
            $totalUsers = $firebaseUsers->count();
    
            // Filtrar usuarios activos (por ejemplo, verificar si tienen un claim 'active')
            $activeUsers = $firebaseUsers->filter(function ($user) {
                // Puedes agregar la lógica para verificar si el usuario está activo
                return isset($user->customClaims['active']) && $user->customClaims['active'] === true;
            });
    
            // Filtrar usuarios baneados (por ejemplo, verificar si tienen un claim 'banned')
            $bannedUsers = $firebaseUsers->filter(function ($user) {
                return isset($user->customClaims['banned']) && $user->customClaims['banned'] === true;
            });
    
            // Filtrar solo los administradores
            $adminUsers = $firebaseUsers->filter(function ($user) {
                return isset($user->customClaims['admin']) && $user->customClaims['admin'] === true;
            });
    
            // Mapeo de administradores para agregar los detalles desde la base de datos
            $adminUsers = $adminUsers->map(function ($user) {
                $localUser = User::where('firebase_uid', $user->uid)->first();
                return [
                    'firebase_uid' => $user->uid,
                    'email' => $user->email,
                    'name' => $localUser->name ?? null,
                    'role' => $localUser->role ?? 'N/A',
                    'created_at' => $localUser->created_at ?? null,
                ];
            })->values();
    
            // Devolver los datos de los usuarios junto con los conteos
            return response()->json([
                'total' => $totalUsers,
                'active' => $activeUsers->count(),
                'banned' => $bannedUsers->count(),
                'admins' => $adminUsers->count(),
                'admin_users' => $adminUsers, // Agregar los datos de los administradores
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener los usuarios: ' . $e->getMessage());
            return response()->json(['error' => 'No se pudieron obtener los usuarios.'], 500);
        }
    }
    

    // Método para crear el primer admin
    public function createFirstAdmin(Request $request)
    {
        // Verificar si ya existe un administrador
        $adminExists = collect($this->auth->listUsers(100))
            ->filter(function ($user) {
                return isset($user->customClaims['admin']) && $user->customClaims['admin'] === true;
            });

        // Si ya hay un administrador, eliminarlo
        if ($adminExists->isNotEmpty()) {
            $existingAdmin = $adminExists->first();

            try {
                // Eliminar al usuario administrador de Firebase
                $this->auth->deleteUser($existingAdmin->uid);

                // Eliminarlo de la base de datos también
                $userToDelete = User::where('firebase_uid', $existingAdmin->uid)->first();
                if ($userToDelete) {
                    $userToDelete->delete();
                }

                return response()->json(['message' => 'Administrador anterior eliminado correctamente.'], 200);
            } catch (\Exception $e) {
                Log::error('Error al eliminar el administrador: ' . $e->getMessage());
                return response()->json(['error' => 'Error al eliminar el administrador: ' . $e->getMessage()], 500);
            }
        }

        // Si no existe un administrador, crear el primero
        $email = $request->input('email');
        $password = $request->input('password');

        try {
            // Crear el usuario en Firebase
            $user = $this->auth->createUser([
                'email' => $email,
                'password' => $password,
                'emailVerified' => true,
            ]);

            // Asignar el claim 'admin' al nuevo usuario
            $this->auth->setCustomUserClaims($user->uid, ['admin' => true]);

            // Guardar el usuario en la base de datos
            User::create([
                'firebase_uid' => $user->uid,
                'name' => 'Admin',
                'email' => $email,
                'password' => Hash::make($password), 
                'role' => 'admin'
            ]);

            return response()->json(['message' => 'Primer admin creado exitosamente.'], 200);
        } catch (\Exception $e) {
            Log::error('Error al crear el primer admin: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Asignar el rol de administrador a un usuario
    public function assignAdminRole($uid)
    {
        try {
            // Verificar que el usuario exista antes de asignar el rol
            $user = $this->auth->getUser($uid);
    
            // Establecer el claim 'admin' como verdadero
            $this->auth->setCustomUserClaims($uid, ['admin' => true]);
    
            return response()->json(['message' => 'Rol de admin asignado correctamente.']);
        } catch (\Exception $e) {
            Log::error('Error al asignar el rol de admin: ' . $e->getMessage());
            return response()->json(['error' => 'Error al asignar el rol de admin: ' . $e->getMessage()], 400);
        }
    }

    // Verificar si un usuario tiene el claim de admin
    public function verifyAdminClaim($userUid)
    {
        try {
            $user = $this->auth->getUser($userUid);

            // Verifica si el claim "admin" existe y es verdadero
            if (isset($user->customClaims['admin']) && $user->customClaims['admin'] === true) {
                return response()->json(['message' => 'Este usuario es un administrador.'], 200);
            } else {
                return response()->json(['message' => 'Este usuario NO es un administrador.'], 200);
            }
        } catch (\Exception $e) {
            Log::error('Error al verificar los claims: ' . $e->getMessage());
            return response()->json(['error' => 'Error al verificar los claims: ' . $e->getMessage()], 500);
        }
    }

     // Fetch recent user registrations
     public function getRecentUsers()
     {
         // Get the most recent users, sorted by registration date (descending order)
         $recentUsers = User::orderBy('created_at', 'desc')
                            ->limit(10)  // Limit to the 10 most recent users
                            ->get(['id', 'name', 'email', 'created_at']);  // Select relevant fields
 
         return response()->json($recentUsers);
     }
    
}
