<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class StructuralPartnerQuery
{
    private const CONTROL = ['tailwind', 'trickroom', 'icywind', 'electroweb', 'thunderwave'];

    private const REDIRECCION = ['followme', 'ragepowder'];

    public function __construct(private TypeChart $chart, private RoleClassifier $roles) {}

    public function forSpecies(
        object $species,
        int $regulationId,
        array $meta,
        array $ritmo,
        array $sinCubrir,
        array $cierres,
        array $excluir,
        int $porCubo,
    ): array {
        $tipos = json_decode((string) $species->types, true) ?: [];
        $debilidades = array_keys($this->chart->weaknesses($tipos));
        $necesita = $ritmo['ritmo'] === 'rapido' ? [] : self::CONTROL;
        $fuera = [...$excluir, $species->slug];

        $candidatos = array_values(array_filter($meta, fn (array $r) => ! in_array($r['slug'], $fuera, true)));
        $slugs = array_column($candidatos, 'slug');
        $papeles = $this->roles->forMany($slugs, $regulationId);
        $aportes = $this->aportes($slugs, $regulationId, [...self::CONTROL, ...self::REDIRECCION]);
        $alcances = $this->alcances($slugs, $regulationId);

        $cubos = array_fill_keys(RoleClassifier::CUBOS, []);

        foreach ($candidatos as $rival) {
            $papel = $papeles[$rival['slug']] ?? ['eje' => 'apoyo', 'etiquetas' => [], 'cubos' => ['apoyo']];
            $razones = $this->razones($rival, $debilidades, $necesita, $sinCubrir, $aportes, $alcances);

            $ficha = [
                'slug' => $rival['slug'],
                'name' => $rival['name'],
                'name_es' => $rival['name_es'],
                'sprite' => $rival['sprite'],
                'sprite_stone' => $rival['sprite_stone'],
                'tipos' => $rival['tipos'],
                'peso' => $rival['peso'],
                'eje' => $papel['eje'],
                'etiquetas' => $papel['etiquetas'],
                'razones' => $razones,
                'encaje' => count($razones),
                'cierre' => $cierres[$rival['slug']] ?? null,
            ];

            foreach ($papel['cubos'] as $cubo) {
                $cubos[$cubo][] = $ficha;
            }
        }

        $usados = [];
        $salida = [];

        foreach (RoleClassifier::ORDEN_RELLENO as $nombre) {
            $lista = $cubos[$nombre] ?? [];

            usort($lista, function (array $a, array $b) use ($nombre, $usados) {
                $repe = [in_array($a['slug'], $usados, true), in_array($b['slug'], $usados, true)];

                if ($repe[0] !== $repe[1]) {
                    return $repe[0] ? 1 : -1;
                }

                return $nombre === 'remate'
                    ? [$b['cierre']['pct'] ?? -1, $b['encaje'], $b['peso']] <=> [$a['cierre']['pct'] ?? -1, $a['encaje'], $a['peso']]
                    : [$b['encaje'], $b['peso']] <=> [$a['encaje'], $a['peso']];
            });

            $elegidos = array_slice($lista, 0, $porCubo);

            if ($elegidos === []) {
                continue;
            }

            $usados = [...$usados, ...array_column($elegidos, 'slug')];
            $salida[$nombre] = $elegidos;
        }

        return $salida;
    }

    private function razones(array $rival, array $debilidades, array $necesita, array $sinCubrir, array $aportes, array $alcances): array
    {
        $razones = [];
        $tapa = $this->tapa($rival['tipos'], $debilidades);

        if ($tapa !== []) {
            $razones[] = ['clave' => 'tapa', 'tipos' => $tapa];
        }

        $suyos = $aportes[$rival['slug']] ?? [];
        $ritmo = array_values(array_intersect_key($suyos, array_flip($necesita)));

        if ($ritmo !== []) {
            $razones[] = ['clave' => 'ritmo', 'movimientos' => $ritmo];
        }

        $redirige = array_values(array_intersect_key($suyos, array_flip(self::REDIRECCION)));

        if ($redirige !== []) {
            $razones[] = ['clave' => 'redirige', 'movimientos' => $redirige];
        }

        $rellena = $this->rellena($alcances[$rival['slug']] ?? [], $sinCubrir);

        if ($rellena !== []) {
            $razones[] = ['clave' => 'cubre', 'tipos' => $rellena];
        }

        return $razones;
    }

    private function rellena(array $tipos, array $sinCubrir): array
    {
        $resueltos = [];

        foreach ($sinCubrir as $duro) {
            foreach ($tipos as $tipo) {
                if ($this->chart->multiplier($tipo, $duro['tipos'] ?? []) >= 2.0) {
                    $resueltos[] = $duro['name'];

                    break;
                }
            }
        }

        return array_slice($resueltos, 0, 4);
    }

    private function tapa(array $tiposRival, array $debilidades): array
    {
        $tapados = [];

        foreach ($debilidades as $debilidad) {
            if ($this->chart->multiplier($debilidad, $tiposRival) < 1.0) {
                $tapados[] = $debilidad;
            }
        }

        return $tapados;
    }

    private function aportes(array $slugs, int $regulationId, array $movimientos): array
    {
        if ($slugs === [] || $movimientos === []) {
            return [];
        }

        $filas = DB::table('learnsets as l')
            ->join('moves as m', 'm.id', '=', 'l.move_id')
            ->join('species as s', 's.id', '=', 'l.species_id')
            ->whereIn('s.slug', $slugs)
            ->where('l.regulation_id', $regulationId)
            ->whereIn('m.slug', $movimientos)
            ->get(['s.slug as especie', 'm.slug as clave', 'm.name as nombre']);

        $salida = [];

        foreach ($filas as $fila) {
            $salida[$fila->especie][$fila->clave] = $fila->nombre;
        }

        return $salida;
    }

    private function alcances(array $slugs, int $regulationId): array
    {
        if ($slugs === []) {
            return [];
        }

        $filas = DB::table('learnsets as l')
            ->join('moves as m', 'm.id', '=', 'l.move_id')
            ->join('species as s', 's.id', '=', 'l.species_id')
            ->whereIn('s.slug', $slugs)
            ->where('l.regulation_id', $regulationId)
            ->where('m.power', '>', 0)
            ->distinct()
            ->get(['s.slug as especie', 'm.type as tipo']);

        $salida = [];

        foreach ($filas as $fila) {
            $salida[$fila->especie][] = $fila->tipo;
        }

        return $salida;
    }
}
