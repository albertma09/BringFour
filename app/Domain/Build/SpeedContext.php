<?php

namespace App\Domain\Build;

final class SpeedContext
{
    public const LENTO = 35.0;

    public const RAPIDO = 65.0;

    public function forSpecies(array $baseStats, array $meta): array
    {
        $velocidad = (int) ($baseStats['spe'] ?? 0);
        $pesos = 0;
        $masLentos = 0;

        foreach ($meta as $rival) {
            $pesos += $rival['peso'];

            if ((int) ($rival['stats']['spe'] ?? 0) < $velocidad) {
                $masLentos += $rival['peso'];
            }
        }

        $percentil = $pesos > 0 ? round(100 * $masLentos / $pesos, 1) : 50.0;

        return [
            'base' => $velocidad,
            'percentil' => $percentil,
            'ritmo' => $this->ritmo($percentil),
            'muestra' => count($meta),
        ];
    }

    public function ritmo(float $percentil): string
    {
        if ($percentil >= self::RAPIDO) {
            return 'rapido';
        }

        return $percentil <= self::LENTO ? 'lento' : 'medio';
    }
}
