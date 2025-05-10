<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airline extends Model
{
    protected $table = 'airlines';

    protected $fillable = [
        'alias',
        'iata_code',
        'icao_code',
        'callsign',
        'country',
        'active'
    ];

    public $timestamps = false;

    // Si quieres usar el campo "active" como booleano real:
    protected $casts = [
        'active' => 'boolean',
    ];
}
