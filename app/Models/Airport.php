<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    use HasFactory;


    protected $table = 'airports';

    protected $fillable = [
        'airport', 'iata', 'icao', 'country_code', 'region_name', 'latitude', 'longitude'
    ];


    public $timestamps = true;

 


}
