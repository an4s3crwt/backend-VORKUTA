<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    use HasFactory;

    // Añade los campos que vas a usar para que se puedan asignar con create() o update()
    protected $fillable = [
        'icao24',
        'callsign',
        'origin_country',
        'last_contact',
        'departure_time',
        'arrival_time',
        'departure_latitude',
        'departure_longitude',
        'arrival_latitude',
        'arrival_longitude',
        'departure_airport',
        'arrival_airport',
        'duration_expected',
        'duration_real',
        'delayed',
        'last_on_ground',
        'last_velocity',
    ];

    // Si usas campos booleanos, puedes indicar casts para que Laravel los maneje bien
    protected $casts = [
        'delayed' => 'boolean',
        'last_on_ground' => 'boolean',
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'last_contact' => 'datetime',
    ];
}
