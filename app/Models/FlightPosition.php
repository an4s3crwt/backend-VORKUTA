<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightPosition extends Model
{
    use HasFactory;
    
    // Permitir guardar estos datos de golpe
    protected $fillable = [
        'icao24', 'latitude', 'longitude', 'velocity', 
        'heading', 'baro_altitude', 'geo_altitude', 
        'on_ground', 'vertical_rate'
    ];
}