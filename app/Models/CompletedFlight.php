<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompletedFlight extends Model
{
    protected $fillable = [
        'icao24', 'callsign', 'origin_country',
        'departure_time', 'departure_latitude', 'departure_longitude',
        'arrival_time', 'arrival_latitude', 'arrival_longitude',
        'duration_real', 'duration_expected', 'delayed'
    ];

    public $timestamps = true;
}
