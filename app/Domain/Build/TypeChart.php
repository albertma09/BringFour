<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class TypeChart
{
    private ?array $tabla = null;

    private ?array $nombres = null;

    public function multiplier(string $atacante, array $defensores): float
    {
        $total = 1.0;

        foreach ($defensores as $defensor) {
            $total *= $this->par($atacante, $defensor);
        }

        return $total;
    }

    public function weaknesses(array $tipos): array
    {
        return $this->porMultiplicador($tipos, fn (float $m) => $m > 1.0);
    }

    public function resistances(array $tipos): array
    {
        return $this->porMultiplicador($tipos, fn (float $m) => $m < 1.0);
    }

    public function types(): array
    {
        $this->cargar();

        return $this->nombres;
    }

    private function porMultiplicador(array $tipos, callable $filtro): array
    {
        $this->cargar();

        $salida = [];

        foreach (array_keys($this->nombres) as $atacante) {
            $multiplicador = $this->multiplier($atacante, $tipos);

            if ($filtro($multiplicador)) {
                $salida[$atacante] = $multiplicador;
            }
        }

        arsort($salida);

        return $salida;
    }

    private function par(string $atacante, string $defensor): float
    {
        $this->cargar();

        return $this->tabla[strtolower($atacante)][strtolower($defensor)] ?? 1.0;
    }

    private function cargar(): void
    {
        if ($this->tabla !== null) {
            return;
        }

        $this->tabla = [];
        $this->nombres = [];

        foreach (DB::table('types')->get(['slug', 'name']) as $tipo) {
            $this->nombres[$tipo->slug] = $tipo->name;
        }

        foreach (DB::table('type_effectiveness')->get() as $fila) {
            $this->tabla[$fila->attacker][$fila->defender] = (float) $fila->multiplier;
        }
    }
}
