<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedFlight;
use App\Models\User;

class SavedFlightController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'flight_icao' => 'required|string',
        'flight_data' => 'required|array',
    ]);

    $user = $request->attributes->get('firebase_user');
    $uid = $user->sub;

    // Retrieve the user from the database
    $dbUser = User::where('firebase_uid', $uid)->first();

    if (!$dbUser) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Evitar duplicados
    $alreadySaved = SavedFlight::where('firebase_uid', $uid)
        ->where('flight_icao', $request->flight_icao)
        ->exists();

    if ($alreadySaved) {
        return response()->json(['message' => 'Flight already saved'], 409);
    }

    $data = $request->flight_data;

    $savedFlight = new SavedFlight();
    $savedFlight->user_id = $dbUser->id;  // Assign user_id from the database
    $savedFlight->firebase_uid = $uid;
    $savedFlight->flight_icao = $request->flight_icao;
    $savedFlight->flight_data = json_encode($data);
    $savedFlight->aircraft_type = $data['aircraft_type'] ?? null;
    $savedFlight->airline_code = $data['airline_code'] ?? null;
    $savedFlight->departure_airport = $data['departure_airport'] ?? null;
    $savedFlight->arrival_airport = $data['arrival_airport'] ?? null;
    $savedFlight->saved_at = now();

    $savedFlight->save();  // The ID is automatically generated here

    return response()->json(['message' => 'Flight saved successfully'], 201);
}


    public function index(Request $request)
    {
        $user = $request->attributes->get('firebase_user');
        $uid = $user->sub;

        $savedFlights = SavedFlight::where('firebase_uid', $uid)->get();

        if ($savedFlights->isEmpty()) {
            return response()->json(['message' => 'No tienes vuelos guardados.'], 404);
        }

        return response()->json(['saved_flights' => $savedFlights]);
    }

    public function indexAll(Request $request)
    {
        $savedFlights = SavedFlight::all();

        if ($savedFlights->isEmpty()) {
            return response()->json(['message' => 'No saved flights found.'], 404);
        }

        return response()->json(['saved_flights' => $savedFlights]);
    }
}
