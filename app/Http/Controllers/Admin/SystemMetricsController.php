<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SystemMetricsController extends Controller
{
    /**
     * USO DE DISCO REAL (Basado en el tamaño de tu Base de Datos 'F')
     * Consulta la 'information_schema' de MySQL para ver cuánto pesan tus tablas.
     */
    public function diskUsage()
    {
        try {
            // Nombre de tu base de datos (según tu output es 'F')
            $dbName = env('DB_DATABASE', 'F');

            // Query real a MySQL para obtener el tamaño en MB
            $size = DB::select("
                SELECT sum(data_length + index_length) / 1024 / 1024 AS size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ", [$dbName]);

            $sizeMb = $size[0]->size_mb ?? 0;
            
            // Asumimos un "presupuesto" de 500MB para la demo del gráfico (puedes cambiarlo)
            $limitMb = 500; 
            $percent = min(100, round(($sizeMb / $limitMb) * 100, 2));

            return response()->json([
                'percent' => $percent,
                'used_mb' => round($sizeMb, 2),
                'label' => round($sizeMb, 2) . ' MB used of SQL Storage'
            ]);

        } catch (\Exception $e) {
            return response()->json(['percent' => 0, 'error' => 'No access to schema']);
        }
    }

    /**
     * USO DE "CPU" / CARGA (Basado en la actividad de `flight_positions` y `logs`)
     * Si hay muchos registros insertándose, la carga es alta.
     */
    public function cpuUsage()
    {
        // 1. Medir "estrés" contando inserciones recientes en logs o posiciones
        // Si tienes timestamps en flight_positions, úsalos. Si no, usamos 'logs' o 'telescope_entries'.
        
        // Contamos entradas en Telescope de los últimos 60 minutos como proxy de carga
        $recentActivity = DB::table('telescope_entries')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        // Escala: Definimos que 1000 peticiones/hora es el 100% de carga (ajusta según tu tráfico)
        $maxLoad = 1000;
        $loadPercent = min(100, round(($recentActivity / $maxLoad) * 100));

        return response()->json([
            'percent' => $loadPercent,
            'details' => "{$recentActivity} requests/hour processed"
        ]);
    }

    /**
     * USO DE MEMORIA / SALUD (Basado en `failed_jobs` vs `performance_logs`)
     */
    public function memoryUsage()
    {
        // Usamos tu tabla 'failed_jobs' para medir la "salud" de la memoria de procesos
        $failed = DB::table('failed_jobs')->count();
        
        // Usamos tu tabla 'performance_logs' para ver si hay consultas lentas
        // Asumiendo que guardas logs ahí. Si está vacía, usamos 0.
        $slowQueries = DB::table('performance_logs')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        // Calculamos un índice de salud inverso
        // Si hay muchos fallos, el "uso" (estrés) sube
        $stressLevel = ($failed * 10) + ($slowQueries * 5);
        $percent = min(100, max(5, $stressLevel)); // Mínimo 5% visual

        return response()->json([
            'percent' => $percent,
            'details' => "{$failed} Failed Jobs / {$slowQueries} Slow Queries"
        ]);
    }
}