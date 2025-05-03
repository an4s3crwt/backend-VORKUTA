<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SavedFlightsController extends Controller
{
    // Listar vuelos guardados
    public function index(Request $request) {
        return response()->json($request->user()->savedFlights);
    }

    // Guardar un vuelo
    public function store(Request $request) {
        $validated = $request->validate([
            'flight_icao' => 'required|string',
            'flight_data' => 'sometimes|array'
        ]);

        $flight = $request->user()->savedFlights()->create($validated);
        return response()->json($flight, 201);
    }

    // Eliminar un vuelo guardado
    public function destroy(Request $request, $icao) {
        $request->user()->savedFlights()->where('flight_icao', $icao)->delete();
        return response()->noContent();
    }
}
