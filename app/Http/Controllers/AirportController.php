<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Airport;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AirportController extends Controller
{
    protected FirebaseAuth $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
        $this->middleware('firebase.auth');
    }

    public function index(Request $request)
    {
        $firebaseUser = $request->attributes->get('firebase_user');
        Log::info('User accessing airports data', ['uid' => $firebaseUser->sub]);

        $validator = Validator::make($request->all(), [
            'letter' => 'nullable|string|size:1|alpha',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $query = Airport::query();

        // Filtro por la primera letra de ciudad o país (ya no por "name")
        if ($request->filled('letter')) {
            $letter = strtoupper($request->letter);
            $query->where(function ($q) use ($letter) {
                $q->where('city', 'LIKE', $letter . '%')
                  ->orWhere('country', 'LIKE', $letter . '%');
            });
        }

        // Solo resultados con país válido
        $query->whereNotNull('country')->where('country', '!=', '');

        // Ordenado por ciudad
        $query->orderBy('city');

        $perPage = $request->input('limit', 50);
        $data = $query->paginate($perPage);

        Log::info('Airports data accessed', [
            'user' => $firebaseUser->sub,
            'page' => $data->currentPage(),
            'letter' => $request->input('letter', 'all')
        ]);

        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'total_pages' => $data->lastPage(),
            'total_items' => $data->total(),
            'requested_by' => $firebaseUser->sub
        ]);
    }
}
