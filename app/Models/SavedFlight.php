<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedFlight extends Model
{
    protected $table = 'saved_flights';

    protected $fillable = [
        'user_id',
        'flight_icao',
        'flight_data',
        'saved_at',
        'firebase_uid',
        'flight_number',
        
    ];

    protected $casts = [
        'flight_data' => 'array',
        'saved_at' => 'datetime',
    ];

    public $timestamps = false;
     // Relación con el modelo User usando firebase_uid
     public function user()
     {
         return $this->belongsTo(User::class, 'firebase_uid', 'firebase_uid');
     }
}
