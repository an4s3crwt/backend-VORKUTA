<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdminUserController extends Controller
{
    /**
     * Listar todos los usuarios (Versión completa con paginación)
     */
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(15);
        return response()->json($users);
    }

    /**
     * Obtener usuarios recientes (Para widgets del dashboard)
     */
    public function getRecentUsers()
    {
        $users = User::orderBy('created_at', 'desc')
                     ->take(5)
                     ->get(['id', 'name', 'email', 'created_at']);
                     
        return response()->json($users);
    }

    /**
     * Asignar Rol de Admin (Toggle)
     * Ruta: /admin/users/{uid}/assign-admin
     */
    public function assignAdminRole($id)
    {
        try {
            // Buscamos por ID local de Laravel
            $user = User::findOrFail($id);

            // Bloqueo de seguridad: No te quites admin a ti mismo
            if ($user->id === auth()->id()) {
                return response()->json(['error' => 'No puedes cambiar tu propio rol.'], 403);
            }

            // Cambiamos el valor booleano
            $user->is_admin = !$user->is_admin;
            $user->save();

            Log::info("Rol de usuario {$user->email} cambiado a " . ($user->is_admin ? 'Admin' : 'User') . " por " . auth()->user()->email);

            return response()->json([
                'message' => 'Rol actualizado correctamente',
                'is_admin' => $user->is_admin
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
    }

    /**
     * 🚨 SALVAVIDAS: Crear el primer admin
     * Útil si borras la base de datos y nadie puede entrar al panel.
     * Ruta pública temporal: POST /api/v1/create-first-admin
     */
    public function createFirstAdmin(Request $request)
    {
        // Validamos un "secreto" simple para que no cualquiera pueda llamar a esto
        if ($request->input('secret_key') !== 'flighty_setup_2025') {
            return response()->json(['message' => 'Unauthorized setup attempt'], 403);
        }

        $email = $request->input('email');
        
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado. Regístrate primero.'], 404);
        }

        $user->is_admin = true;
        $user->save();

        return response()->json(['message' => "Usuario {$user->name} ahora es SUPER ADMIN."]);
    }
    
    // NOTA: Métodos 'assignAdminClaim' y 'verifyAdminClaim' 
    // Si usas Firebase Custom Claims (avanzado), irían aquí. 
    // Pero con el booleano 'is_admin' en la DB local (arriba) es suficiente y más fácil de defender.
    public function assignAdminClaim($uid) {
        return response()->json(['message' => 'Usando gestión de roles local (DB)']);
    }
}