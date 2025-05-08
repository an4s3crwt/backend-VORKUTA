<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightView extends Model
{
    // Especificamos el nombre de la tabla si no sigue la convención de nombres pluralizados
    protected $table = 'flight_views';

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'callsign', 'flight_number', 'from_airport_code', 
        'to_airport_code', 'firebase_uid'
    ];

    // Si no estás usando marcas de tiempo (created_at, updated_at), lo puedes deshabilitar
    public $timestamps = false; // Si no tienes timestamps automáticos

 
public function user()
{
    return $this->belongsTo(User::class, 'firebase_uid', 'firebase_uid');
}

    // Relación con el modelo Airport (asumiendo que tienes un modelo Airport)
  
}
