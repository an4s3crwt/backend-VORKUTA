<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Kreait\Firebase\Auth as FirebaseAuth;
use Laravel\Sanctum\HasApiTokens; // <-- LÍNEA AÑADIDA (1/2)

class User extends Authenticatable
{
    // Asegúrate de que HasApiTokens sea la primera trait o esté incluida aquí.
    use HasApiTokens, HasFactory, Notifiable, HasRoles; // <-- LÍNEA MODIFICADA (2/2)

    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'firebase_data',
        'photo_url',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'firebase_data' => 'array', // Cast to array for easy access
    ];

    // Helper method to check if user is admin
    public function isAdmin()
    {
        return $this->firebase_data['customClaims']['admin'] ?? false;
    }

    // Method to sync with Firebase user data
    public function syncWithFirebase(FirebaseAuth $auth = null)
    {
        if (!$auth) {
            $auth = app(FirebaseAuth::class);
        }

        try {
            $firebaseUser = $auth->getUser($this->firebase_uid);
            $this->firebase_data = [
                'email_verified' => $firebaseUser->emailVerified,
                'customClaims' => $firebaseUser->customClaims ?? [],
                'metadata' => [
                    'created_at' => $firebaseUser->metadata->createdAt,
                    'last_login_at' => $firebaseUser->metadata->lastLoginAt,
                ],
                'provider_data' => $firebaseUser->providerData,
            ];
            
            $this->save();
            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    protected $attributes = [
        'role' => 'user', // Valor por defecto
    ];

    public function savedFlights()
{
    return $this->hasMany(SavedFlight::class, 'firebase_uid', 'firebase_uid');
}

}