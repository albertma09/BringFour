<?php

namespace App\Domain\Stats;

final class SpreadAdvisor
{
    public const TOTAL = 66;

    public const TOPE = 32;

    private const OFENSIVO = 100;

    private const RAPIDO = 80;

    private const LENTO = 55;

    private const REPARTOS = [
        'ofensivo_rapido' => ['ofensiva' => 32, 'spe' => 32, 'hp' => 2],
        'ofensivo_medio' => ['ofensiva' => 32, 'spe' => 24, 'hp' => 10],
        'ofensivo_lento' => ['ofensiva' => 32, 'hp' => 32, 'defensa' => 2],
        'apoyo_rapido' => ['hp' => 32, 'spe' => 32, 'defensa' => 2],
        'apoyo_medio' => ['hp' => 32, 'spe' => 20, 'defensa' => 14],
        'apoyo_lento' => ['hp' => 32, 'defensa' => 32, 'spe' => 2],
    ];

    public function suggest(array $baseStats, ?string $plus = null, ?string $minus = null): array
    {
        $ofensiva = $this->ofensiva($baseStats, $plus, $minus);
        $papel = max((int) ($baseStats['atk'] ?? 0), (int) ($baseStats['spa'] ?? 0)) >= self::OFENSIVO ? 'ofensivo' : 'apoyo';
        $ritmo = $this->ritmo($baseStats, $plus, $minus);
        $defensa = ((int) ($baseStats['def'] ?? 0)) <= ((int) ($baseStats['spd'] ?? 0)) ? 'def' : 'spd';

        $sp = ['hp' => 0, 'atk' => 0, 'def' => 0, 'spa' => 0, 'spd' => 0, 'spe' => 0];
        $razones = [];

        foreach (self::REPARTOS[$papel.'_'.$ritmo] as $ranura => $puntos) {
            $stat = match ($ranura) {
                'ofensiva' => $ofensiva,
                'defensa' => $defensa,
                default => $ranura,
            };

            $sp[$stat] += $puntos;
            $razones[] = ['stat' => $stat, 'clave' => $this->clave($ranura, $puntos), 'sp' => $puntos];
        }

        return [
            'sp' => $sp,
            'total' => array_sum($sp),
            'papel' => $papel,
            'ritmo' => $ritmo,
            'ofensiva' => $ofensiva,
            'defensa' => $defensa,
            'razones' => $razones,
        ];
    }

    private function ofensiva(array $baseStats, ?string $plus, ?string $minus): string
    {
        if ($plus === 'atk' || $minus === 'spa') {
            return 'atk';
        }

        if ($plus === 'spa' || $minus === 'atk') {
            return 'spa';
        }

        return ((int) ($baseStats['spa'] ?? 0)) > ((int) ($baseStats['atk'] ?? 0)) ? 'spa' : 'atk';
    }

    private function ritmo(array $baseStats, ?string $plus, ?string $minus): string
    {
        if ($minus === 'spe') {
            return 'lento';
        }

        if ($plus === 'spe') {
            return 'rapido';
        }

        $base = (int) ($baseStats['spe'] ?? 0);

        if ($base >= self::RAPIDO) {
            return 'rapido';
        }

        return $base <= self::LENTO ? 'lento' : 'medio';
    }

    private function clave(string $ranura, int $puntos): string
    {
        if ($puntos <= 2) {
            return 'sobrante';
        }

        return match ($ranura) {
            'ofensiva' => 'ofensiva',
            'defensa' => 'defensa',
            'hp' => 'aguante',
            default => 'velocidad',
        };
    }
}
