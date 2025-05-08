<?php

namespace App\Console\Commands;
use App\Http\Middleware\CheckAdmin;
use Illuminate\Console\Command;
use App\Models\User;
use Kreait\Laravel\Firebase\Facades\Firebase;

use Kreait\Firebase\Auth\UserRecord;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin {email} {password}';
    protected $description = 'Create admin user in both Firebase and Laravel';

    
public function handle()
{
    $email = $this->argument('email');
    $password = $this->argument('password');

    try {
        // 1. Crear usuario en Firebase
        $auth = Firebase::auth();
        $firebaseUser = $auth->createUser([
            'email' => $email,
            'password' => $password,
            'emailVerified' => true,
        ]);
        
        // 2. Establecer claim de admin
        $auth->setCustomUserClaims($firebaseUser->uid, ['admin' => true]);
        
        $this->info("Usuario creado en Firebase con claims. UID: ".$firebaseUser->uid);

        // 3. Crear usuario en Laravel
        $admin = User::create([
            'name' => 'Admin',
            'email' => $email,
            'firebase_uid' => $firebaseUser->uid,
            'password' => bcrypt($password),
            'role' => 'admin' // Mantener consistencia con tu DB
        ]);

        $this->info("Usuario ADMIN creado exitosamente en ambos sistemas");
        $this->info("Firebase UID: ".$firebaseUser->uid);
        $this->info("Email: ".$email);
        $this->info("Password: ".$password);

    } catch (\Exception $e) {
        $this->error("Error: ".$e->getMessage());
    }
}
}