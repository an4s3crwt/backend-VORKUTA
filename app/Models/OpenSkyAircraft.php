<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenSkyAircraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'icao24', 'callsign', 'origin_country',
        'longitude', 'latitude', 'baro_altitude',
        'velocity', 'time_position'
    ];
}
