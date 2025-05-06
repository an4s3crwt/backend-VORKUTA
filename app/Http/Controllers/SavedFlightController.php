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
            'flight_data' => 'nullable|array',
        ]);

        $user = $request->attributes->get('firebase_user'); // Si estás usando auth Firebase
        $uid = $user->sub; // UID de Firebase

        $alreadySaved = SavedFlight::where('user_uid', $uid)
            ->where('flight_icao', $request->flight_icao)
            ->exists();

        if ($alreadySaved) {
            return response()->json(['message' => 'Vuelo ya guardado.'], 409);
        }

        $flight = new SavedFlight();
        $flight->user_uid = $uid;
        $flight->flight_icao = $request->flight_icao;
        $flight->flight_data = $request->flight_data ?? [];
        $flight->saved_at = now();
        $flight->save();

        return response()->json(['message' => 'Vuelo guardado.'], 201);
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
