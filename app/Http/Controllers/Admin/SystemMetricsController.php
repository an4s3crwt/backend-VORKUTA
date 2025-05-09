<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SystemMetricsController extends Controller
{
    // Endpoint para obtener el uso de la CPU
    public function cpuUsage()
    {
        try {
            $process = new Process(['top', '-bn1']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            
            // Mejor expresión regular para diferentes formatos de top
            if (preg_match('/%Cpu\(s\):\s*([\d\.]+)\s*us,\s*([\d\.]+)\s*sy/', $output, $matches)) {
                $cpuUsage = (float)$matches[1] + (float)$matches[2]; // usuario + sistema
            } elseif (preg_match('/Cpu\(s\):\s*([\d\.]+)%id/', $output, $matches)) {
                $cpuUsage = 100 - (float)$matches[1]; // 100 - idle
            } else {
                throw new \RuntimeException('Formato de salida de CPU no reconocido');
            }

            return response()->json([
                'cpu_usage' => round($cpuUsage, 2),
                'raw_output' => $output // Para debugging
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener el uso de CPU: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el uso de CPU.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // Endpoint para obtener el uso de la memoria
    public function memoryUsage()
    {
        try {
            $process = new Process(['free', '-m']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            
            // Mejor manejo de la salida de free
            if (preg_match('/Mem:\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)/', $output, $matches)) {
                $totalMemory = (int)$matches[1];
                $usedMemory = (int)$matches[2];
                $memoryUsage = ($usedMemory / $totalMemory) * 100;
            } else {
                throw new \RuntimeException('Formato de salida de memoria no reconocido');
            }

            return response()->json([
                'memory_usage' => round($memoryUsage, 2),
                'total_memory' => $totalMemory,
                'used_memory' => $usedMemory
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener el uso de memoria: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el uso de memoria.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // Endpoint para obtener el espacio en disco
    public function diskUsage()
    {
        try {
            // Usar -P para formato POSIX y evitar saltos de línea
            $process = new Process(['df', '-P', '/']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            
            // Mejor expresión regular para df
            if (preg_match('/\S+\s+\d+\s+\d+\s+\d+\s+(\d+)%\s+\S+/', $output, $matches)) {
                $diskUsage = (float)$matches[1];
            } else {
                throw new \RuntimeException('Formato de salida de disco no reconocido: '.$output);
            }

            return response()->json([
                'disk_usage' => $diskUsage,
                'raw_output' => $output // Para debugging
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener el uso de disco: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el uso de disco.',
                'details' => $e->getMessage(),
                'raw_output' => isset($output) ? $output : null
            ], 500);
        }
    }
}