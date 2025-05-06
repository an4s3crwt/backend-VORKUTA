<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedFlight extends Model
{
    protected $table = 'saved_flights';

    protected $fillable = [
        'user_uid',
        'flight_icao',
        'flight_data',
        'saved_at',
    ];

    protected $casts = [
        'flight_data' => 'array',
        'saved_at' => 'datetime',
    ];

    public $timestamps = false;
}
