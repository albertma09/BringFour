<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class StructuralPartnerQuery
{
    private const CONTROL = ['tailwind', 'trickroom', 'icywind', 'electroweb', 'thunderwave'];

    private const REDIRECCION = ['followme', 'ragepowder'];

    private const TOPE_ALCANCE = 9;

    public function __construct(private TypeChart $chart, private RoleClassifier $roles, private FieldEffects $field) {}

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

        return $this->repartir($cubos, $porCubo);
    }

    public function forTeam(array $analisis, int $regulationId, array $meta, array $cierres, int $porCubo, array $golpes = []): array
    {
        $intocables = $analisis['ofensiva']['intocables'] ?? [];
        $dentro = array_column($analisis['miembros'], 'slug');
        $compartidas = array_column($analisis['compartidas'], 'cuantos', 'tipo');
        $sinResistir = array_column($analisis['sin_resistir'], 'peso', 'tipo');
        $faltan = $analisis['papeles']['faltan'];
        $necesita = $analisis['velocidad']['sin_control'] ? self::CONTROL : [];
        $campo = $analisis['campo'] ?? ['clima' => null, 'terreno' => null, 'huerfanos' => []];

        $candidatos = array_values(array_filter($meta, fn (array $r) => ! in_array($r['slug'], $dentro, true)));
        $slugs = array_column($candidatos, 'slug');
        $papeles = $this->roles->forMany($slugs, $regulationId);
        $aportes = $this->aportes($slugs, $regulationId, [...self::CONTROL, ...self::REDIRECCION]);
        $habilidades = $this->habilidades($slugs);

        $cubos = array_fill_keys(RoleClassifier::CUBOS, []);

        foreach ($candidatos as $rival) {
            $papel = $papeles[$rival['slug']] ?? ['eje' => 'apoyo', 'etiquetas' => [], 'cubos' => ['apoyo']];
            $razones = $this->razonesDeEquipo($rival, $papel, $compartidas, $sinResistir, $faltan, $necesita, $aportes);
            $suCampo = $this->porCampo($rival, $habilidades[$rival['slug']] ?? [], $campo);

            if ($suCampo !== null) {
                $razones['lista'][] = $suCampo;
                $razones['valor'] += 4;
            }

            $alcance = $this->alcanza($golpes[$rival['slug']] ?? [], $intocables);

            if ($alcance !== null) {
                $razones['lista'][] = $alcance['razon'];
                $razones['valor'] += $alcance['valor'];
            }

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
                'razones' => $razones['lista'],
                'encaje' => $razones['valor'],
                'cierre' => $cierres[$rival['slug']] ?? null,
            ];

            foreach ($papel['cubos'] as $cubo) {
                $cubos[$cubo][] = $ficha;
            }
        }

        return $this->repartir($cubos, $porCubo);
    }

    private function alcanza(array $golpes, array $intocables): ?array
    {
        if ($golpes === [] || $intocables === []) {
            return null;
        }

        $resueltos = [];

        foreach ($intocables as $intocable) {
            foreach ($golpes as $tipo) {
                if ($this->chart->multiplier($tipo, $intocable['tipos']) >= 2.0) {
                    $resueltos[] = $intocable['name'];

                    break;
                }
            }
        }

        if ($resueltos === []) {
            return null;
        }

        return [
            'razon' => ['clave' => 'alcanza', 'tipos' => array_slice($resueltos, 0, 3)],
            'valor' => min(self::TOPE_ALCANCE, 3 * count($resueltos)),
        ];
    }

    private function porCampo(array $rival, array $habilidades, array $campo): ?array
    {
        $doble = $this->field->speedMultiplier($habilidades, $campo['clima'] ?? null);

        if ($doble['x'] > 1.0) {
            return ['clave' => 'campo_velocidad', 'movimientos' => [$doble['habilidad']]];
        }

        foreach (array_filter([$campo['clima'] ?? null, $campo['terreno'] ?? null]) as $activo) {
            foreach ($rival['tipos'] as $tipo) {
                if ($this->field->boosts($activo, $tipo)) {
                    return ['clave' => 'campo_golpe', 'tipos' => [$activo]];
                }
            }
        }

        foreach ($campo['huerfanos'] ?? [] as $huerfano) {
            $puesto = $this->field->sets($habilidades);

            if (($puesto['campo'] ?? null) === $huerfano['necesita']) {
                return ['clave' => 'campo_pone', 'tipos' => [$huerfano['necesita']]];
            }
        }

        return null;
    }

    private function habilidades(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return DB::table('species')
            ->whereIn('slug', $slugs)
            ->pluck('abilities', 'slug')
            ->map(fn ($json) => array_values(json_decode((string) $json, true) ?: []))
            ->all();
    }

    private function razonesDeEquipo(array $rival, array $papel, array $compartidas, array $sinResistir, array $faltan, array $necesita, array $aportes): array
    {
        $lista = [];
        $valor = 0;
        $tapa = [];

        foreach ($compartidas as $tipo => $cuantos) {
            if ($this->chart->multiplier($tipo, $rival['tipos']) < 1.0) {
                $tapa[] = $tipo;
                $valor += $cuantos * 3;
            }
        }

        if ($tapa !== []) {
            $lista[] = ['clave' => 'tapa', 'tipos' => $tapa];
        }

        $huecos = [];

        foreach ($sinResistir as $tipo => $peso) {
            if ($this->chart->multiplier($tipo, $rival['tipos']) < 1.0) {
                $huecos[] = $tipo;
                $valor += 1;
            }
        }

        if ($huecos !== []) {
            $lista[] = ['clave' => 'hueco', 'tipos' => array_slice($huecos, 0, 4)];
        }

        $suyos = $aportes[$rival['slug']] ?? [];
        $ritmo = array_values(array_intersect_key($suyos, array_flip($necesita)));

        if ($ritmo !== []) {
            $lista[] = ['clave' => 'ritmo', 'movimientos' => $ritmo];
            $valor += 2;
        }

        $aporta = array_values(array_intersect($faltan, $papel['etiquetas']));

        if ($aporta !== []) {
            $lista[] = ['clave' => 'papel', 'tipos' => $aporta];
            $valor += 2 * count($aporta);
        }

        return ['lista' => $lista, 'valor' => $valor];
    }

    private function repartir(array $cubos, int $porCubo): array
    {
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
