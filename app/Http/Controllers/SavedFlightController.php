<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedFlight;

class SavedFlightController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'flight_icao' => 'required|string',
            'flight_data' => 'required|array',
        ]);
    
        $user = auth()->user();
    
        // Evitar duplicados
        $alreadySaved = SavedFlight::where('user_uid', $user->uid)
            ->where('flight_icao', $request->flight_icao)
            ->exists();
    
        if ($alreadySaved) {
            return response()->json(['message' => 'Flight already saved'], 409);
        }
    
        $data = $request->flight_data;
    
        $savedFlight = new SavedFlight();
        $savedFlight->user_uid = $user->uid;
        $savedFlight->flight_icao = $request->flight_icao;
        $savedFlight->flight_data = json_encode($data); // Guarda todo igualmente
        $savedFlight->aircraft_type = $data['aircraft_type'] ?? null;
        $savedFlight->airline_code = $data['airline_code'] ?? null;
        $savedFlight->departure_airport = $data['departure_airport'] ?? null;
        $savedFlight->arrival_airport = $data['arrival_airport'] ?? null;
        $savedFlight->saved_at = now();
    
        $savedFlight->save();
    
        return response()->json(['message' => 'Flight saved successfully'], 201);
    }
    



    /**
     * Summary of index
     * @param \Illuminate\Http\Request $request
     * @return mixed|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Obtener el UID del usuario autenticado desde el token de Firebase
        $user = $request->attributes->get('firebase_user');
        $uid = $user->sub;

        // Obtener todos los vuelos guardados por este usuario
        $savedFlights = SavedFlight::where('user_uid', $uid)->get();

        if ($savedFlights->isEmpty()) {
            return response()->json(['message' => 'No tienes vuelos guardados.'], 404);
        }

        return response()->json(['saved_flights' => $savedFlights]);
    } 

}
