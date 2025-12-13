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
    public function getAiLogs()
    {
        $flights = ['IBE324', 'VLG102', 'RYR99', 'UAE55', 'Lufthansa 404'];
        $logs = [];

        foreach ($flights as $index => $flight) {
            $scenario = rand(1, 4);
            $data = [];

            switch ($scenario) {
                case 1: 
                    $data = ['prediction' => 'on_time', 'minutes' => 0, 'prob' => rand(5, 20) / 100, 'reason' => 'Stable flight at 34000ft. On schedule.'];
                    break;
                case 2: 
                    $prob = rand(70, 95) / 100;
                    $mins = 15 + ($prob * 30);
                    $data = ['prediction' => 'delayed', 'minutes' => round($mins, 1), 'prob' => $prob, 'reason' => "High risk detected. Delay: " . round($mins, 1) . " min."];
                    break;
                case 3: 
                    $data = ['prediction' => 'potential_delay', 'minutes' => 0, 'prob' => 0.0, 'reason' => 'Signal lost or data not updating (Frozen Radar).'];
                    break;
                case 4: 
                    $data = ['prediction' => 'on_time', 'minutes' => 2.0, 'prob' => 0.30, 'reason' => 'Final approach at 2000ft. Landing imminent.'];
                    break;
            }

            $logs[] = [
                'id' => rand(1000, 9999),
                'flight' => $flight,
                'time' => now()->subMinutes($index * 2)->format('H:i:s'),
                'prediction' => $data['prediction'],
                'minutes' => $data['minutes'],
                'prob' => $data['prob'],
                'reason' => $data['reason']
            ];
        }

        return response()->json($logs);
    }

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
}