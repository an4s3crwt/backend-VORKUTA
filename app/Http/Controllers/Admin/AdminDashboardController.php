<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; // NECESARIO PARA PING
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class AdminDashboardController extends Controller
{
    /**
     * 1. MONITOR DE INFRAESTRUCTURA (KPIs)
     */
    public function getSystemStats()
    {
        $stats = [
            'totalUsers' => DB::table('users')->count(),
            'flightPositions' => DB::table('flight_positions')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'logsCount' => DB::table('telescope_entries')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * 2. LOGS DE IA (Simulados)
     */
  
    /**
     * 3. MONITOR DE SEGURIDAD (Usuarios + Logs)
     */
    public function getRecentUsers()
    {
        $users = User::orderBy('updated_at', 'desc')->take(10)->get();

        $data = $users->map(function($user) {
            // 1. Recuperar Fecha (JSON robusto)
            $loginDate = $user->last_login;
            if (!$loginDate && $user->firebase_data) {
                try {
                    $jsonData = is_string($user->firebase_data) ? json_decode($user->firebase_data, true) : $user->firebase_data;
                    if (isset($jsonData['metadata']['last_login_at']['date'])) {
                        $loginDate = $jsonData['metadata']['last_login_at']['date'];
                    }
                } catch (\Exception $e) {}
            }

            // 2. Recuperar IP (Tabla Logs)
            $lastLog = DB::table('logs')->where('user_id', $user->id)->orderBy('created_at', 'desc')->first();

            // 3. Estado Online
            $isOnline = false;
            if ($loginDate) {
                $lastActivity = Carbon::parse($loginDate);
                if ($lastActivity->diffInMinutes(now()) < 10) $isOnline = true;
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role, 
                'is_banned' => (bool)$user->is_banned,
                'last_login' => $loginDate ? Carbon::parse($loginDate)->diffForHumans() : 'Registro reciente',
                'ip' => $lastLog ? $lastLog->ip_address : null, // Enviamos null para que el frontend ponga 'No Data'
                'status' => $isOnline ? 'online' : 'offline'
            ];
        });

        return response()->json($data);
    }

    /**
     * 4. GESTIÓN DE ROLES
     */
    public function toggleRole($id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) return response()->json(['message' => 'No puedes cambiar tu propio rol'], 403);

        $user->is_admin = !$user->is_admin;
        $user->role = $user->is_admin ? 'admin' : 'user'; 
        $user->save();

        return response()->json(['message' => 'Rol actualizado', 'newRole' => $user->role]);
    }

    /**
     * 5. BORRADO DE USUARIOS (MEJORADO)
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Protección Admin
        if ($user->role === 'admin') {
             return response()->json(['message' => 'No se puede eliminar a un administrador.'], 403);
        }

        $user->delete();
        // Limpieza de logs asociados (opcional pero recomendable)
        DB::table('logs')->where('user_id', $id)->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    /**
     * 6. DETALLE DE ERRORES (Failed Jobs)
     */
    public function getFailedJobsDetail()
    {
        $jobs = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->take(10)->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $commandName = $payload['displayName'] ?? 'Unknown Job';
                $exceptionLines = explode("\n", $job->exception);
                $shortError = implode("\n", array_slice($exceptionLines, 0, 2));

                return [
                    'id' => $job->id,
                    'job_name' => $commandName,
                    'error_preview' => $shortError, 
                    'failed_at' => Carbon::parse($job->failed_at)->diffForHumans(),
                    'full_exception' => $job->exception 
                ];
            });

        return response()->json($jobs);
    }

    /**
     * 7. PING REAL A OPENSKY 
     */
   /**
     * 7. PING REAL A OPENSKY (CON CREDENCIALES .ENV)
     */
    public function checkOpenSkyStatus()
    {
        $start = microtime(true);
        
        try {
            // 1. Recuperamos credenciales del archivo .env
            $username = env('OPENSKY_USERNAME');
            $password = env('OPENSKY_PASSWORD');

            // 2. Preparamos la petición
            $request = Http::timeout(10)->withoutVerifying(); // Timeout 10s y SSL fix

            // 3. Si hay credenciales, las inyectamos (Basic Auth)
            if ($username && $password) {
                $request->withBasicAuth($username, $password);
            }

            // 4. Hacemos la petición (Zona de Suiza para que sea ligera)
            $response = $request->get('https://opensky-network.org/api/states/all?lamin=45.8389&lomin=5.9962&lamax=47.8229&lomax=10.5226');
            
            $duration = round((microtime(true) - $start) * 1000); 
            
            if ($response->successful()) {
                return response()->json([
                    'status' => 'online', 
                    'latency' => $duration, 
                    'message' => 'Authenticated Connection Successful (' . ($username ? 'User' : 'Anon') . ')'
                ]);
            } else {
                return response()->json([
                    'status' => 'degraded', 
                    'latency' => $duration, 
                    'message' => 'API Error: ' . $response->status() . ' - ' . $response->reason()
                ], 503);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'offline', 
                'latency' => 0, 
                'message' => 'Laravel Connection Failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * HERRAMIENTAS DE SISTEMA (Ejecutar comandos Artisan)
     * Endpoint: POST /api/v1/admin/system/{action}
     */
    public function runSystemAction($action)
    {
        // Solo permitimos acciones seguras específicas
        $allowedActions = [
            'clear_cache' => 'cache:clear',
            'clear_config' => 'config:clear',
            'optimize' => 'optimize:clear',
            'migrate_status' => 'migrate:status'
        ];

        if (!array_key_exists($action, $allowedActions)) {
            return response()->json(['message' => 'Acción no permitida'], 400);
        }

        try {
            // Ejecutamos el comando
            Artisan::call($allowedActions[$action]);
            
            // Capturamos la salida de texto de la terminal
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => "Comando '{$allowedActions[$action]}' ejecutado.",
                'output' => trim($output)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error crítico al ejecutar comando: ' . $e->getMessage()
            ], 500);
        }
    }

  /**
     * DATOS REALES DE RENDIMIENTO (Modificado: Recientes + Lentos)
     */
    public function getRealPerformanceStats()
    {
        // 1. PETICIONES RECIENTES (Live Traffic)
        // Sacamos las últimas 6 que han entrado al sistema
        $recent = DB::table('performance_logs')
            ->select(
                'path as endpoint', 
                'method', 
                'response_time', 
                'status_code',
                'created_at'
            )
            ->orderBy('created_at', 'desc') // Las más nuevas primero
            ->limit(6)
            ->get()
            ->map(function ($log) {
                // Formateamos la fecha para que sea legible
                $log->time_ago = \Carbon\Carbon::parse($log->created_at)->diffForHumans();
                return $log;
            });

        // 2. TOP 5 ENDPOINTS LENTOS (Igual que antes)
        $slowest = DB::table('performance_logs')
            ->select(
                'path as endpoint', 
                'method', 
                DB::raw('AVG(response_time) as avg_time'), 
                DB::raw('COUNT(*) as calls')
            )
            ->groupBy('path', 'method')
            ->orderByDesc('avg_time')
            ->limit(5)
            ->get();

        return response()->json([
            'recent' => $recent,   // <--- CAMBIO AQUÍ (Antes era 'chart')
            'slowest' => $slowest
        ]);
    }

    // En tu endpoint /admin/db-stats o uno nuevo
public function serverStats()
{
    // 1. RAM (Memoria) - Solo funciona en Linux
    $ramUsage = 0;
    try {
        // Ejecutamos 'free -m' para obtener megas usados
        $free = shell_exec('free -m');
        $free = (string)trim($free);
        $arr = explode("\n", $free);
        // Parseamos la segunda línea que tiene los datos
        $mem = preg_split("/\s+/", $arr[1]); 
        // $mem[1] es Total, $mem[2] es Usada
        $ramUsage = ($mem[2] / $mem[1]) * 100;
    } catch (\Exception $e) {
        $ramUsage = 0; // Fallback por si estás en Windows
    }

    // 2. CPU (Carga del sistema)
    // sys_getloadavg() devuelve la carga de 1, 5 y 15 min.
    // Usamos la de 1 minuto. Multiplicamos por 100 para porcentaje aprox.
    $load = sys_getloadavg();
    $cpuUsage = $load[0] * 100;
    // Si la carga es mayor a 100% (multinúcleo), lo limitamos visualmente a 100
    if ($cpuUsage > 100) $cpuUsage = 100;

    // 3. DISCO DURO (Espacio en partición raíz)
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    $diskUsage = 100 - (($diskFree / $diskTotal) * 100);

    return response()->json([
        'cpu' => round($cpuUsage, 1),
        'ram' => round($ramUsage, 1),
        'disk' => round($diskUsage, 1),
        'apiQuota' => 65 // Este lo dejamos fijo o lo sacas de tu contador de API
    ]);
}
}