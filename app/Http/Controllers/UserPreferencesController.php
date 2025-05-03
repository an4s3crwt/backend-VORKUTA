<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\Request;

class UserPreferencesController extends Controller
{
    
     // Obtener preferencias del usuario
     public function index(Request $request) {
        $preferences = $request->user()->preferences ?? new UserPreference(); //refers to the model
        return response()->json($preferences);
    }

    // Guardar preferencias (tema y filtros)
    public function update(Request $request) {
        $validated = $request->validate([
            'map_theme' => 'sometimes|in:light,dark,satellite',
            'map_filters' => 'sometimes|array'
        ]);

        $preferences = $request->user()->preferences()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json($preferences);
    }
}
