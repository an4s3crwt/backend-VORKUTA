<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    protected $auth;

    // Inyectamos el servicio de Firebase Auth
    public function __construct(FirebaseAuth $auth)
    {
        $this->auth = $auth;
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
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function assignAdminClaim($userUid)
    {
        try {
            // Asigna el claim "admin" al usuario con el UID proporcionado
            $this->auth->setCustomUserClaims($userUid, ['admin' => true]);

            return response()->json(['message' => 'El usuario ahora es un administrador.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al asignar el claim de admin: ' . $e->getMessage()], 500);
        }
    }

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
            return response()->json(['error' => 'Error al verificar los claims: ' . $e->getMessage()], 500);
        }
    }


}

