<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Airline;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;

class AirlineController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
        $this->middleware('firebase.auth');
    }

   public function index(Request $request)
{
    $firebaseUser = $request->attributes->get('firebase_user');
    Log::info('User accessing airlines data', ['uid' => $firebaseUser->sub]);

    $validator = \Validator::make($request->all(), [
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

    $query = Airline::query();

    // Filtrar por la letra inicial de 'alias', 'callsign' o 'country'
    if ($request->has('letter') && strlen($request->letter) === 1) {
        $letter = strtoupper($request->letter);
        $query->where(function($q) use ($letter) {
            $q->where('alias', 'LIKE', $letter.'%')
              ->orWhere('callsign', 'LIKE', $letter.'%')
              ->orWhere('country', 'LIKE', $letter.'%');
        });
    }

    $query->whereNotNull('country')->where('country', '!=', '');
    $query->orderBy('alias'); // Ordenar por 'alias', puedes cambiar esto si lo prefieres por otro campo

    $perPage = $request->has('limit') ? $request->limit : 50;
    $data = $query->paginate($perPage);

    Log::info('Airlines data accessed', [
        'user' => $firebaseUser->sub,
        'page' => $data->currentPage(),
        'letter' => $request->letter ?? 'all'
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
