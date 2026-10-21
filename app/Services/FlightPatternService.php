<?php

namespace App\Services;

class FlightPatternService
{
    /**
     * Evalúa la telemetría del avión mediante cálculo físico determinista.
     * Retorna la estructura exacta que espera tu Frontend y tu tabla ai_logs.
     */
    public function evaluateFlight(array $telemetry): array
    {
        $velocity = (float)($telemetry['velocity'] ?? 0.0);
        $verticalRate = abs((float)($telemetry['vertical_rate'] ?? 0.0));
        $baroAltitude = (float)($telemetry['baro_altitude'] ?? 0.0);
        $onGround = (bool)($telemetry['on_ground'] ?? false);

        // Si la aeronave está en tierra o no tiene altitud de vuelo
        if ($onGround || $baroAltitude < 500) {
            return [
                'success' => true,
                'status' => 'NORMAL',
                'predicted_probability' => 0.05,
                'delay_minutes' => 0,
                'explanation' => 'Aeronave en maniobras de superficie o aproximación final standard.'
            ];
        }

        // Criterios aeronáuticos para patrón de espera (Holding):
        // 1. Tasa de ascenso/descenso cercana a 0 (mantiene nivel de vuelo asignado).
        // 2. Velocidad reducida de espera (típicamente < 230 nudos / ~118 m/s).
        $isLevelFlight = $verticalRate < 1.5; // Menos de 1.5 m/s de variación vertical
        $isHoldingSpeed = $velocity > 60 && $velocity < 125; // Velocidad de espera en m/s

        if ($isLevelFlight && $isHoldingSpeed) {
            return [
                'success' => true,
                'status' => 'HOLDING_DETECTED',
                'predicted_probability' => 0.88,
                'delay_minutes' => 15,
                'explanation' => 'Patrón de espera detectado: velocidad reducida sostenida y altitud de crucero constante.'
            ];
        }

        if ($verticalRate > 8.0) {
            return [
                'success' => true,
                'status' => 'MANEUVERING',
                'predicted_probability' => 0.35,
                'delay_minutes' => 5,
                'explanation' => 'Maniobra de cambio de nivel rápido detectada.'
            ];
        }

        return [
            'success' => true,
            'status' => 'ON_SCHEDULE',
            'predicted_probability' => 0.10,
            'delay_minutes' => 0,
            'explanation' => 'Vuelo en crucero estándar sin anomalías de trayectoria.'
        ];
    }
}