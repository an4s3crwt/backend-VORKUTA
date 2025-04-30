<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class OpenSkyController extends Controller
{
    public function getStatesAll(Request $request){
        try{
            $username= config('services.opensky.username');
            $password = config('services.opensky.password');

            $response = Http::withBasicAuth($username, $password)
                ->timeout(30)
                ->get('https://opensky-network.org/api/states/all');


            if($response->failed()){
                return response()->json([
                    'error' => 'Error al obtener datos de OpenSky',
                    'details' => $response->json()
                ], $response->status());
            }

            return response()->json($response->json());
        }catch(\Exception $e){
            return response()->json([
                'error' => 'Error en el servidor',
                'details' => $e->getMessage()
            ], 500);

        }
    }


    
}
