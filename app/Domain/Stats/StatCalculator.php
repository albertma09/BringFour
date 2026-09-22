<?php

namespace App\Domain\Stats;

use InvalidArgumentException;

class StatCalculator
{
    public const LEVEL = 50;

    public const SP_TOTAL = 66;

    public const SP_PER_STAT_CAP = 32;

    public const STATS = ['hp', 'atk', 'def', 'spa', 'spd', 'spe'];

    public function stat(string $stat, int $baseStat, int $sp, ?string $plusStat = null, ?string $minusStat = null): int
    {
        if (! in_array($stat, self::STATS, true)) {
            throw new InvalidArgumentException("Estadistica desconocida: {$stat}");
        }

        if ($sp < 0 || $sp > self::SP_PER_STAT_CAP) {
            throw new InvalidArgumentException("SP fuera de rango para {$stat}: {$sp}");
        }

        if ($stat === 'hp') {
            return $baseStat + 75 + $sp;
        }

        $value = $baseStat + 20 + $sp;

        return match ($this->modifier($stat, $plusStat, $minusStat)) {
            11 => intdiv($value * 11, 10),
            9 => intdiv($value * 9, 10),
            default => $value,
        };
    }

    public function spread(array $baseStats, array $sp, ?string $plusStat = null, ?string $minusStat = null): array
    {
        $result = [];

        foreach (self::STATS as $stat) {
            $result[$stat] = $this->stat(
                $stat,
                $baseStats[$stat] ?? throw new InvalidArgumentException("Falta base stat: {$stat}"),
                $sp[$stat] ?? 0,
                $plusStat,
                $minusStat,
            );
        }

        return $result;
    }

    public function modifier(string $stat, ?string $plusStat, ?string $minusStat): int
    {
        if ($stat === 'hp' || $plusStat === null || $minusStat === null || $plusStat === $minusStat) {
            return 10;
        }

        return match ($stat) {
            $plusStat => 11,
            $minusStat => 9,
            default => 10,
        };
    }

    public function spToEvs(int $sp): int
    {
        if ($sp <= 0) {
            return 0;
        }

        return 4 + (($sp - 1) * 8);
    }

    public function evsToSp(int $evs): int
    {
        if ($evs < 4) {
            return 0;
        }

        return min(self::SP_PER_STAT_CAP, 1 + (int) floor(($evs - 4) / 8));
    }
}
