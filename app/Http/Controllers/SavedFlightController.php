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
}
