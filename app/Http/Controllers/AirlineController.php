<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Airline;
class AirlineController extends Controller
{
    public function index()
    {
        return response()->json(
            Airline::orderBy('country')->paginate(50)
        );
    }
}
