<?php

namespace App\Domain\Stats;

final class Proportion
{
    public const Z95 = 1.959963984540054;

    public static function wilson(int $successes, int $total, float $z = self::Z95): array
    {
        if ($total <= 0) {
            return [0.0, 1.0];
        }

        $p = $successes / $total;
        $z2 = $z * $z;
        $denominator = 1 + $z2 / $total;
        $centre = ($p + $z2 / (2 * $total)) / $denominator;
        $margin = $z * sqrt($p * (1 - $p) / $total + $z2 / (4 * $total * $total)) / $denominator;

        return [
            round(max(0.0, $centre - $margin), 12),
            round(min(1.0, $centre + $margin), 12),
        ];
    }

    public static function differsFrom(int $successes, int $total, float $baseline, float $z = self::Z95): bool
    {
        if ($total <= 0) {
            return false;
        }

        [$low, $high] = self::wilson($successes, $total, $z);

        return $baseline < $low || $baseline > $high;
    }
}
