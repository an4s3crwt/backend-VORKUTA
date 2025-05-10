<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Flight;


class FlightController extends Controller
{
     public function index()
    {
        $flights = Flight::with(['airline', 'departureAirport', 'arrivalAirport'])->orderBy('last_seen', 'desc')->get();
        return response()->json($flights);
    }

    public function show($id)
    {
        $flight = Flight::with(['airline', 'departureAirport', 'arrivalAirport'])->findOrFail($id);
        return response()->json($flight);
    }
}
