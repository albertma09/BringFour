<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class TeamAnalyzer
{
    public const PAPELES = ['fisico', 'especial', 'velocidad', 'redireccion', 'remate'];

    private const COMPARTIDA = 2;

    private const SESGO = 0.75;

    public function __construct(private TypeChart $chart, private RoleClassifier $roles, private SpeedContext $speed) {}

    public function analyse(array $especies, int $formatId, int $elo, int $regulationId, array $meta, array $pesos, array $cierres): array
    {
        $miembros = $this->miembros($especies, $regulationId, $cierres, $meta);

        return [
            'miembros' => $miembros,
            'papeles' => $this->papeles($miembros),
            'reparto' => $this->reparto($miembros),
            'velocidad' => $this->velocidad($miembros, $meta),
            'compartidas' => $this->compartidas($miembros, $pesos),
            'sin_resistir' => $this->sinResistir($miembros, $pesos),
            'amenazas' => $this->amenazas($miembros, $formatId, $elo, $meta),
        ];
    }

    private function miembros(array $especies, int $regulationId, array $cierres, array $meta): array
    {
        $papeles = $this->roles->forMany(array_column($especies, 'slug'), $regulationId);
        $pesoDe = array_column($meta, 'peso', 'slug');
        $salida = [];

        foreach ($especies as $especie) {
            $tipos = json_decode((string) $especie->types, true) ?: [];
            $stats = json_decode((string) $especie->base_stats, true) ?: [];
            $papel = $papeles[$especie->slug] ?? ['eje' => 'apoyo', 'etiquetas' => [], 'cubos' => []];

            $salida[] = [
                'slug' => $especie->slug,
                'name' => $especie->name,
                'name_es' => $especie->name_es,
                'sprite' => $especie->sprite_file,
                'sprite_stone' => $especie->sprite_stone_slug,
                'tipos' => $tipos,
                'stats' => $stats,
                'eje' => $papel['eje'],
                'etiquetas' => $papel['etiquetas'],
                'debilidades' => array_keys($this->chart->weaknesses($tipos)),
                'resistencias' => array_keys($this->chart->resistances($tipos)),
                'cierre' => $cierres[$especie->slug] ?? null,
                'peso' => $pesoDe[$especie->slug] ?? 0,
            ];
        }

        return $salida;
    }

    private function papeles(array $miembros): array
    {
        $tiene = [];

        foreach ($miembros as $miembro) {
            if (in_array($miembro['eje'], ['fisico', 'mixto'], true)) {
                $tiene[] = 'fisico';
            }

            if (in_array($miembro['eje'], ['especial', 'mixto'], true)) {
                $tiene[] = 'especial';
            }

            foreach (['velocidad', 'redireccion', 'remate'] as $etiqueta) {
                if (in_array($etiqueta, $miembro['etiquetas'], true)) {
                    $tiene[] = $etiqueta;
                }
            }
        }

        $tiene = array_values(array_unique($tiene));

        return ['tiene' => $tiene, 'faltan' => array_values(array_diff(self::PAPELES, $tiene))];
    }

    private function reparto(array $miembros): array
    {
        $fisicos = count(array_filter($miembros, fn (array $m) => in_array($m['eje'], ['fisico', 'mixto'], true)));
        $especiales = count(array_filter($miembros, fn (array $m) => in_array($m['eje'], ['especial', 'mixto'], true)));
        $atacantes = max(1, $fisicos + $especiales);

        return [
            'fisicos' => $fisicos,
            'especiales' => $especiales,
            'sesgado' => count($miembros) >= 3 && max($fisicos, $especiales) / $atacantes >= self::SESGO
                ? ($fisicos > $especiales ? 'fisico' : 'especial')
                : null,
        ];
    }

    private function velocidad(array $miembros, array $meta): array
    {
        $percentiles = array_map(fn (array $m) => $this->speed->forSpecies($m['stats'], $meta)['percentil'], $miembros);
        $media = $percentiles === [] ? 50.0 : round(array_sum($percentiles) / count($percentiles), 1);

        $conControl = array_filter($miembros, fn (array $m) => in_array('velocidad', $m['etiquetas'], true));

        return [
            'percentil' => $media,
            'perfil' => $this->speed->ritmo($media),
            'con_control' => array_values(array_map(fn (array $m) => $m['name'], $conControl)),
            'sin_control' => $conControl === [],
        ];
    }

    private function compartidas(array $miembros, array $pesos): array
    {
        if (count($miembros) < self::COMPARTIDA) {
            return [];
        }

        $porTipo = [];

        foreach ($miembros as $miembro) {
            foreach ($miembro['debilidades'] as $tipo) {
                $porTipo[$tipo][] = $miembro['name'];
            }
        }

        $salida = [];

        foreach ($porTipo as $tipo => $afectados) {
            if (count($afectados) < self::COMPARTIDA) {
                continue;
            }

            $resisten = count(array_filter($miembros, fn (array $m) => in_array($tipo, $m['resistencias'], true)));

            $salida[] = [
                'tipo' => $tipo,
                'miembros' => $afectados,
                'cuantos' => count($afectados),
                'resisten' => $resisten,
                'peso' => $pesos[$tipo] ?? 0.0,
                'gravedad' => round(count($afectados) * ($pesos[$tipo] ?? 0.0), 1),
            ];
        }

        usort($salida, fn (array $a, array $b) => $b['gravedad'] <=> $a['gravedad']);

        return $salida;
    }

    private function sinResistir(array $miembros, array $pesos): array
    {
        $salida = [];

        foreach ($this->chart->types() as $tipo => $nombre) {
            $resisten = array_filter($miembros, fn (array $m) => in_array($tipo, $m['resistencias'], true));

            if ($resisten !== [] || ($pesos[$tipo] ?? 0.0) <= 0.0) {
                continue;
            }

            $salida[] = ['tipo' => $tipo, 'peso' => $pesos[$tipo]];
        }

        usort($salida, fn (array $a, array $b) => $b['peso'] <=> $a['peso']);

        return $salida;
    }

    private function amenazas(array $miembros, int $formatId, int $elo, array $meta): array
    {
        $vistos = $this->golpesVistos($formatId, $elo);
        $salida = [];

        foreach ($meta as $rival) {
            $suyos = $vistos[$rival['slug']] ?? [];

            if ($suyos === [] || count($miembros) === 0) {
                continue;
            }

            $toca = [];
            $golpe = null;

            foreach ($miembros as $miembro) {
                if ($miembro['slug'] === $rival['slug']) {
                    continue;
                }

                foreach ($suyos as $tipo => $datos) {
                    if ($this->chart->multiplier($tipo, $miembro['tipos']) >= 2.0) {
                        $toca[] = $miembro['name'];
                        $golpe ??= $datos;

                        break;
                    }
                }
            }

            if (count($toca) < self::COMPARTIDA) {
                continue;
            }

            $salida[] = [
                'slug' => $rival['slug'],
                'name' => $rival['name'],
                'name_es' => $rival['name_es'],
                'sprite' => $rival['sprite'],
                'sprite_stone' => $rival['sprite_stone'],
                'tipos' => $rival['tipos'],
                'peso' => $rival['peso'],
                'movimiento' => $golpe['name'] ?? null,
                'movimiento_es' => $golpe['name_es'] ?? null,
                'n' => $golpe['n'] ?? null,
                'toca' => $toca,
            ];
        }

        usort($salida, fn (array $a, array $b) => [count($b['toca']), $b['peso']] <=> [count($a['toca']), $a['peso']]);

        return array_slice($salida, 0, 10);
    }

    private function golpesVistos(int $formatId, int $elo): array
    {
        $filas = DB::select('
            select s.slug, lower(m.type) as tipo, m.name, m.name_es, count(*) as n
            from replay_actions a
            join replay_turns rt on rt.id = a.replay_turn_id
            join replays r on r.id = rt.replay_id
            join moves m on m.id = a.move_id
            join species s on s.id = a.actor_species_id
            where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ? and m.power > 0
            group by s.slug, lower(m.type), m.name, m.name_es
            order by count(*) desc
        ', [$formatId, $elo]);

        $salida = [];

        foreach ($filas as $fila) {
            $salida[$fila->slug][$fila->tipo] ??= [
                'name' => $fila->name,
                'name_es' => $fila->name_es,
                'n' => (int) $fila->n,
            ];
        }

        return $salida;
    }
}
