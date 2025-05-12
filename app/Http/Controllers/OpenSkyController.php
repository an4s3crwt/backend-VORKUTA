<?php
namespace App\Http\Controllers;

use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class OpenSkyController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    /**
     * Método helper para validar Firebase UID
     */
    // Método para validar UID de Firebase
    public function validateFirebaseUid(Request $request)
    {
        $uid = $request->input('uid'); // Suponiendo que el UID viene en el cuerpo de la solicitud

        try {
            // Intentar obtener el usuario con el UID
            $user = $this->firebaseAuth->getUser($uid);
            return response()->json(['user' => $user], 200); // Si todo está bien, devolver los detalles del usuario
        } catch (UserNotFound $e) {
            // Si no se encuentra el usuario en Firebase
            Log::error('User not found in Firebase with UID: ' . $uid);
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            // Cualquier otro error
            Log::error('Error fetching Firebase user: ' . $e->getMessage());
            return response()->json(['error' => 'Error fetching Firebase user'], 500);
        }
    }
    /**
     * Método para obtener todos los estados de vuelos (requiere autenticación Firebase)
     */
    public function getStatesAll(Request $request)
    {
        try {
            $response = Http::withBasicAuth(env('OPENSKY_USERNAME'), env('OPENSKY_PASSWORD'))
                ->timeout(15)
                ->get('https://opensky-network.org/api/states/all');

            if ($response->failed()) {
                Log::error('OpenSky API failed: ' . $response->status());
                return response()->json(['error' => 'Failed to fetch flight data'], 500);
            }

            $data = $response->json();

            if (!isset($data['states'])) {
                return response()->json(['error' => 'Invalid data format from API'], 500);
            }

            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return $flight[0] && $flight[1] && $flight[5] && $flight[6]; // ICAO24, callsign, lat, lon
                })
                ->take(1000)
                ->values()
                ->all();

            return response()->json([
                'time' => $data['time'] ?? time(),
                'states' => array_values($filteredStates),
            ]);

        } catch (\Exception $e) {
            Log::error('getStatesAll error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Método para obtener datos en tiempo real filtrados
     */
    public function fetchLiveData(Request $request)
    {
        try {
            $username = config('services.opensky.username');
            $password = config('services.opensky.password');

            $response = Http::withBasicAuth($username, $password)
                ->timeout(20)
                ->get('https://opensky-network.org/api/states/all');

            if ($response->failed()) {
                Log::warning('OpenSky API failed with status: ' . $response->status());
                return response()->json(['error' => 'Service unavailable'], 503);
            }

            $data = $response->json();

            if (!isset($data['states'])) {
                return response()->json(['error' => 'Invalid data format'], 500);
            }

            $filteredStates = collect($data['states'])
                ->filter(function ($flight) {
                    return $flight[0] && $flight[1] && $flight[5] && $flight[6];
                })
                ->take(200)
                ->values()
                ->all();

            return response()->json([
                'time' => $data['time'],
                'states' => $filteredStates,
            ]);

        } catch (\Exception $e) {
            Log::error('fetchLiveData error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Método para escanear datos en segundo plano
     */
    public function scanLiveData(Request $request)
    {
        try {
            $username = config('services.opensky.username');
            $password = config('services.opensky.password');

            $response = Http::withBasicAuth($username, $password)
                ->timeout(30)
                ->get('https://opensky-network.org/api/states/all');

            if ($response->failed()) {
                Log::warning('OpenSky scan failed with status: ' . $response->status());
                return response()->json(['error' => 'Service unavailable'], 503);
            }

            $data = $response->json();

            if (!isset($data['states'])) {
                return response()->json(['error' => 'Invalid data format'], 500);
            }

            return response()->json([
                'message' => 'Scan completed successfully',
                'count' => count($data['states']),
                'timestamp' => $data['time']
            ]);

        } catch (\Exception $e) {
            Log::error('scanLiveData error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }





}