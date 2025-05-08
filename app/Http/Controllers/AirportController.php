<?php


namespace App\Http\Controllers;

use App\Models\Airport;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    /**
     * Crear un nuevo aeropuerto.
     */
    public function store(Request $request)
{
    // Validar los datos que recibimos
    $validatedData = $request->validate([
        'airport' => 'required|string|max:255',
        'iata' => 'required|string|max:10',
        'icao' => 'required|string|max:10',
        'country_code' => 'required|string|max:5',
        'region_name' => 'required|string|max:255',
        'latitude' => 'required|decimal:10,7',
        'longitude' => 'required|decimal:10,7',
    ]);

    // Almacenar los datos del aeropuerto
    $airport = Airport::create($validatedData);

    return response()->json($airport, 201);
}

}
