<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class TeamOffense
{
    public const REDUNDANTE = 3;

    public const POBRE = 70.0;

    private const SUPER = 2.0;

    private const RARO = 0.05;

    private const MINIMO_GOLPE = 3;

    private const INTOCABLES = 10;

    private const ANADIDOS = 3;

    private const MINIMO = 4;

    public function __construct(private TypeChart $chart, private SetBuilder $builder) {}

    public function analyse(array $especies, int $formatId, int $elo, int $regulationId, array $meta, int $min): array
    {
        $fuentes = $this->fuentes($especies, $formatId, $elo, $regulationId, $meta, $min);
        $tipos = $this->reunir($fuentes);
        $cobertura = $this->cobertura($tipos, $meta);

        return [
            'pct' => $cobertura['pct'],
            'pobre' => count($especies) >= self::MINIMO && $cobertura['pct'] < self::POBRE,
            'intocables' => $cobertura['intocables'],
            'repetidos' => $this->repetidos($fuentes, $meta),
            'mejor_anadido' => $this->mejorAnadido($tipos, $meta, $cobertura['pct']),
            'fuentes' => $fuentes,
            'tipos' => $tipos,
            'meta' => ['especies' => count($meta), 'traidas' => array_sum(array_column($meta, 'peso'))],
        ];
    }

    public function cobertura(array $tipos, array $meta): array
    {
        if ($meta === []) {
            return ['pct' => 0.0, 'intocables' => []];
        }

        $total = 0;
        $alcanzados = 0;
        $intocables = [];

        foreach ($meta as $rival) {
            $total += $rival['peso'];
            $mejor = $this->mejor($tipos, $rival['tipos']);

            if ($mejor >= self::SUPER) {
                $alcanzados += $rival['peso'];

                continue;
            }

            $intocables[] = [
                'slug' => $rival['slug'],
                'name' => $rival['name'],
                'name_es' => $rival['name_es'],
                'sprite' => $rival['sprite'],
                'sprite_stone' => $rival['sprite_stone'],
                'tipos' => $rival['tipos'],
                'peso' => $rival['peso'],
                'x' => $mejor,
                'inmune' => $this->inmuniza($tipos, $rival['tipos']),
            ];
        }

        usort($intocables, fn (array $a, array $b) => $b['peso'] <=> $a['peso']);

        return [
            'pct' => $total > 0 ? round(100 * $alcanzados / $total, 1) : 0.0,
            'intocables' => array_slice($intocables, 0, self::INTOCABLES),
        ];
    }

    public function huecos(array $especies, int $formatId, int $elo, int $regulationId, array $meta, int $min): array
    {
        $fuentes = $this->fuentes($especies, $formatId, $elo, $regulationId, $meta, $min);

        return $this->cobertura($this->reunir($fuentes), $meta)['intocables'];
    }

    private function fuentes(array $especies, int $formatId, int $elo, int $regulationId, array $meta, int $min): array
    {
        $vistos = $this->vistos(array_map(fn (object $e) => $e->slug, $especies), $formatId, $elo);
        $salida = [];

        foreach ($especies as $especie) {
            $suyos = $vistos[$especie->slug] ?? [];
            $usos = array_sum($suyos);

            $ficha = [
                'slug' => $especie->slug,
                'name' => $especie->name,
                'name_es' => $especie->name_es,
                'n' => $usos,
            ];

            $salida[] = $usos >= $min
                ? $ficha + ['origen' => 'visto', 'tipos' => $this->frecuentes($suyos, $usos)]
                : $ficha + ['origen' => 'deducido', 'tipos' => $this->deducidos($especie, $regulationId, $meta)];
        }

        return $salida;
    }

    public function tiposVistos(array $slugs, int $formatId, int $elo): array
    {
        $salida = [];

        foreach ($this->vistos($slugs, $formatId, $elo) as $slug => $suyos) {
            $salida[$slug] = $this->frecuentes($suyos, array_sum($suyos));
        }

        return $salida;
    }

    private function vistos(array $slugs, int $formatId, int $elo): array
    {
        if ($slugs === []) {
            return [];
        }

        $filas = DB::table('replay_actions as a')
            ->join('replay_turns as rt', 'rt.id', '=', 'a.replay_turn_id')
            ->join('replays as r', 'r.id', '=', 'rt.replay_id')
            ->join('moves as m', 'm.id', '=', 'a.move_id')
            ->join('species as s', 's.id', '=', 'a.actor_species_id')
            ->where('r.format_id', $formatId)
            ->whereRaw('coalesce(r.elo_bucket, 0) >= ?', [$elo])
            ->where('m.power', '>', 0)
            ->whereIn('s.slug', $slugs)
            ->groupBy('s.slug', 'm.type')
            ->get(['s.slug', 'm.type as tipo', DB::raw('count(*) as n')]);

        $salida = [];

        foreach ($filas as $fila) {
            $salida[$fila->slug][strtolower((string) $fila->tipo)] = (int) $fila->n;
        }

        return $salida;
    }

    private function frecuentes(array $suyos, int $usos): array
    {
        $suelo = max(self::MINIMO_GOLPE, $usos * self::RARO);

        return array_keys(array_filter($suyos, fn (int $n) => $n >= $suelo));
    }

    private function deducidos(object $especie, int $regulationId, array $meta): array
    {
        $conjunto = $this->builder->build($especie, $regulationId, $meta);

        $tipos = array_map(
            fn (array $m) => strtolower((string) $m['type']),
            array_filter($conjunto['movimientos'], fn (array $m) => ($m['power'] ?? 0) > 0),
        );

        return array_values(array_unique($tipos));
    }

    private function reunir(array $fuentes): array
    {
        $tipos = [];

        foreach ($fuentes as $fuente) {
            $tipos = [...$tipos, ...$fuente['tipos']];
        }

        return array_values(array_unique($tipos));
    }

    private function repetidos(array $fuentes, array $meta): array
    {
        $porTipo = [];

        foreach ($fuentes as $fuente) {
            foreach ($fuente['tipos'] as $tipo) {
                $porTipo[$tipo][] = $fuente['name'];
            }
        }

        $salida = [];

        foreach ($porTipo as $tipo => $quienes) {
            if (count($quienes) >= self::REDUNDANTE && $this->sirve($tipo, $meta)) {
                $salida[] = ['tipo' => $tipo, 'quienes' => $quienes, 'cuantos' => count($quienes)];
            }
        }

        usort($salida, fn (array $a, array $b) => $b['cuantos'] <=> $a['cuantos']);

        return $salida;
    }

    private function mejorAnadido(array $tipos, array $meta, float $actual): array
    {
        $salida = [];

        foreach (array_keys($this->chart->types()) as $candidato) {
            if (in_array($candidato, $tipos, true)) {
                continue;
            }

            $ganancia = round($this->cobertura([...$tipos, $candidato], $meta)['pct'] - $actual, 1);

            if ($ganancia > 0.0) {
                $salida[] = ['tipo' => $candidato, 'ganancia' => $ganancia];
            }
        }

        usort($salida, fn (array $a, array $b) => $b['ganancia'] <=> $a['ganancia']);

        return array_slice($salida, 0, self::ANADIDOS);
    }

    private function sirve(string $tipo, array $meta): bool
    {
        foreach ($meta as $rival) {
            if ($this->chart->multiplier($tipo, $rival['tipos']) >= self::SUPER) {
                return true;
            }
        }

        return false;
    }

    private function mejor(array $tipos, array $defensores): float
    {
        $mejor = 0.0;

        foreach ($tipos as $tipo) {
            $mejor = max($mejor, $this->chart->multiplier($tipo, $defensores));
        }

        return $mejor;
    }

    private function inmuniza(array $tipos, array $defensores): bool
    {
        foreach ($tipos as $tipo) {
            if ($this->chart->multiplier($tipo, $defensores) === 0.0) {
                return true;
            }
        }

        return false;
    }
}
