<?php

namespace App\Domain\Build;

final class SpeedContext
{
    public function __construct(private FieldEffects $field) {}

    public const LENTO = 35.0;

    public const RAPIDO = 65.0;

    public function forSpecies(array $baseStats, array $meta, array $habilidades = [], ?string $clima = null): array
    {
        $base = (int) ($baseStats['spe'] ?? 0);
        $doble = $this->field->speedMultiplier($habilidades, $clima);
        $velocidad = (int) round($base * $doble['x']);
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
            'base' => $base,
            'efectiva' => $velocidad,
            'percentil' => $percentil,
            'ritmo' => $this->ritmo($percentil),
            'muestra' => count($meta),
            'doblada' => $doble['habilidad'],
            'necesita_clima' => $this->field->needsWeather($habilidades),
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
