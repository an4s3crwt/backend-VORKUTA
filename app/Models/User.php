<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Kreait\Firebase\Auth as FirebaseAuth;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'firebase_data', // For storing additional Firebase user data
        'photo_url',      // If you want to store Firebase profile photo
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