<?php

namespace App\Domain\Stats;

class SpSpread
{
    public const TOTAL = StatCalculator::SP_TOTAL;

    public const CAP = StatCalculator::SP_PER_STAT_CAP;

    public static function errors(array $sp): array
    {
        $errors = [];

        foreach (StatCalculator::STATS as $stat) {
            $value = $sp[$stat] ?? 0;

            if (! is_int($value)) {
                $errors[] = "sp.{$stat} debe ser un entero";

                continue;
            }

            if ($value < 0) {
                $errors[] = "sp.{$stat} no puede ser negativo";
            }

            if ($value > self::CAP) {
                $errors[] = "sp.{$stat} excede el tope de ".self::CAP." ({$value})";
            }
        }

        $unknown = array_diff(array_keys($sp), StatCalculator::STATS);
        foreach ($unknown as $stat) {
            $errors[] = "sp.{$stat} no es una estadistica valida";
        }

        if ($errors === []) {
            $total = self::total($sp);

            if ($total > self::TOTAL) {
                $errors[] = 'el total de SP es '.$total.', el maximo es '.self::TOTAL;
            }
        }

        return $errors;
    }

    public static function isValid(array $sp): bool
    {
        return self::errors($sp) === [];
    }

    public static function total(array $sp): int
    {
        $total = 0;

        foreach (StatCalculator::STATS as $stat) {
            $total += $sp[$stat] ?? 0;
        }

        return $total;
    }

    public static function remaining(array $sp): int
    {
        return self::TOTAL - self::total($sp);
    }

    public static function normalize(array $sp): array
    {
        $out = [];

        foreach (StatCalculator::STATS as $stat) {
            $out[$stat] = (int) ($sp[$stat] ?? 0);
        }

        return $out;
    }
}
