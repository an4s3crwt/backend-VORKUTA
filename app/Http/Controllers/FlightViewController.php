<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FlightView;
class FlightViewController extends Controller
{
    
    public function storeFlightView(Request $request)
    {
        $data = $request->only([
            'callsign',
            'flight_number',
            'from_airport_code',
            'to_airport_code',
            'firebase_uid',
        ]);
    
        // Handle null values
        $data['from_airport_code'] = $data['from_airport_code'] === "Unknown" ? null : $data['from_airport_code'];
        $data['to_airport_code'] = $data['to_airport_code'] === "Unknown" ? null : $data['to_airport_code'];
    
        FlightView::create($data);
    
        return response()->json(['message' => 'Flight view stored'], 201);
    }
}
