<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    // Listar todas las claves de caché
    public function index()
    {
        $keys = $this->getAllCacheKeys();
        $cacheData = [];
        
        foreach ($keys as $key) {
            $cacheData[] = [
                'key' => $key,
                'value' => Cache::get($key),
                'ttl' => Cache::getTtl($key),
            ];
        }
        
        return response()->json($cacheData);
    }

    // Obtener valor de una clave específica
    public function show($key)
    {
        if (!Cache::has($key)) {
            return response()->json(['error' => 'Key not found'], 404);
        }
        
        return response()->json([
            'key' => $key,
            'value' => Cache::get($key),
            'ttl' => Cache::getTtl($key),
        ]);
    }

    // Borrar una clave de caché
    public function destroy($key)
    {
        if (!Cache::has($key)) {
            return response()->json(['error' => 'Key not found'], 404);
        }
        
        Cache::forget($key);
        
        return response()->json(['message' => 'Cache key deleted successfully']);
    }

    // Refrescar el caché (ejemplo con vuelos)
    public function refresh()
    {
        // Aquí iría la lógica para regenerar tus datos en caché
        // Por ejemplo:
        $flights = Flight::all(); // Suponiendo que tienes un modelo Flight
        Cache::put('flights', $flights, now()->addHours(1));
        
        return response()->json(['message' => 'Cache refreshed successfully']);
    }

    // Método auxiliar para obtener todas las claves (depende del driver de caché)
    private function getAllCacheKeys()
    {
        // Esto funciona con Redis, para otros drivers necesitarás adaptarlo
        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            return $redis->keys('*');
        }
        
        // Para otros drivers, podrías mantener un registro de las claves en la base de datos
        return [];
    }
}