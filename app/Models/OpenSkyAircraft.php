<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenSkyAircraft extends Model
{
    use HasFactory;
  // Define the table associated with the model
  protected $table = 'flight_data';

  // The attributes that are mass assignable
  protected $fillable = [
      'icao',          // ICAO identifier for the aircraft
      'callsign',      // Callsign of the flight
      'latitude',      // Latitude of the aircraft
      'longitude',     // Longitude of the aircraft
      'altitude',      // Altitude of the aircraft
      'speed',         // Speed of the aircraft
  ];

  // Define timestamps if needed
  public $timestamps = true;
}
