<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Crear el rol
        $role = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'api',
        ]);

        // Asignarlo a un usuario existente (ID 1)
        $user = User::find(1);
        if ($user && !$user->hasRole('user')) {
            $user->assignRole($role);
        }
    }
}
