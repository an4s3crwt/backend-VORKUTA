<?php

namespace App\Http\Controllers;
use App\Models\Airport;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function index()
    {
        return response()->json(
            Airport::orderBy('country')->paginate(50)
        );
    }
}
