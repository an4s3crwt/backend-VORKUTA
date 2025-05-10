<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $table = 'airports';

    protected $fillable = [
        'city',
        'country',
        'iata_code',
        'icao_code',
        'latitude',
        'longitude',
        'altitude',
        'timezone_offset',
        'dst',
        'tz_database_timezone',
        'airport_type',
        'source'
    ];

    public $timestamps = false;
}
