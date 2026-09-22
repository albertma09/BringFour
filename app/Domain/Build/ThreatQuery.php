<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class ThreatQuery
{
    public function __construct(private TypeChart $chart) {}

    public function forSpecies(object $species, int $formatId, int $elo, int $regulationId, array $meta, int $top): array
    {
        $tipos = json_decode((string) $species->types, true) ?: [];
        $stats = json_decode((string) $species->base_stats, true) ?: [];
        $velocidad = (int) ($stats['spe'] ?? 0);
        $debilidades = $this->chart->weaknesses($tipos);

        $vistos = $this->vistos($formatId, $elo, array_keys($debilidades));
        $confirmadas = [];
        $posibles = [];

        foreach ($meta as $rival) {
            if ($rival['slug'] === $species->slug) {
                continue;
            }

            $antes = (int) ($rival['stats']['spe'] ?? 0) > $velocidad;
            $visto = $vistos[$rival['slug']] ?? null;

            if ($visto !== null) {
                $confirmadas[] = $this->fila($rival, $visto, $debilidades, $antes, $visto['n']);

                continue;
            }

            $posible = $this->delRepertorio($rival, $regulationId, $debilidades);

            if ($posible !== null) {
                $posibles[] = $this->fila($rival, $posible, $debilidades, $antes, null);
            }
        }

        usort($confirmadas, fn (array $a, array $b) => [$b['n'], $b['x']] <=> [$a['n'], $a['x']]);
        usort($posibles, fn (array $a, array $b) => [$b['peso'], $b['x']] <=> [$a['peso'], $a['x']]);

        return [
            'velocidad_base' => $velocidad,
            'debilidades' => $debilidades,
            'resistencias' => $this->chart->resistances($tipos),
            'confirmadas' => array_slice($confirmadas, 0, $top),
            'posibles' => array_slice($posibles, 0, $top),
        ];
    }

    private function vistos(int $formatId, int $elo, array $tipos): array
    {
        if ($tipos === []) {
            return [];
        }

        $marcas = implode(', ', array_fill(0, count($tipos), '?'));

        $filas = DB::select("
            select s.slug, m.name, m.name_es, m.type, count(*) as n
            from replay_actions a
            join replay_turns rt on rt.id = a.replay_turn_id
            join replays r on r.id = rt.replay_id
            join moves m on m.id = a.move_id
            join species s on s.id = a.actor_species_id
            where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
              and lower(m.type) in ({$marcas}) and m.power > 0
            group by s.slug, m.name, m.name_es, m.type
            order by count(*) desc
        ", [$formatId, $elo, ...$tipos]);

        $salida = [];

        foreach ($filas as $fila) {
            $salida[$fila->slug] ??= [
                'name' => $fila->name,
                'name_es' => $fila->name_es,
                'type' => $fila->type,
                'n' => (int) $fila->n,
            ];
        }

        return $salida;
    }

    private function delRepertorio(array $rival, int $regulationId, array $debilidades): ?array
    {
        $stats = $rival['stats'];
        $categoria = ((int) ($stats['spa'] ?? 0)) > ((int) ($stats['atk'] ?? 0)) ? 'Special' : 'Physical';

        $filas = DB::select('
            select m.name, m.name_es, m.type
            from learnsets l
            join moves m on m.id = l.move_id
            join species s on s.id = l.species_id
            where s.slug = ? and l.regulation_id = ? and m.category = ?
              and m.power > 0 and lower(m.type) in ('.implode(', ', array_fill(0, count($debilidades), '?')).')
            order by m.power desc
            limit 1
        ', [$rival['slug'], $regulationId, $categoria, ...array_keys($debilidades)]);

        if ($filas === []) {
            return null;
        }

        return [
            'name' => $filas[0]->name,
            'name_es' => $filas[0]->name_es,
            'type' => $filas[0]->type,
        ];
    }

    private function fila(array $rival, array $golpe, array $debilidades, bool $antes, ?int $n): array
    {
        return [
            'slug' => $rival['slug'],
            'name' => $rival['name'],
            'name_es' => $rival['name_es'],
            'sprite' => $rival['sprite'],
            'sprite_stone' => $rival['sprite_stone'],
            'tipos' => $rival['tipos'],
            'peso' => $rival['peso'],
            'movimiento' => $golpe['name'],
            'movimiento_es' => $golpe['name_es'],
            'tipo_golpe' => $golpe['type'],
            'x' => $debilidades[strtolower($golpe['type'])] ?? 2.0,
            'antes' => $antes,
            'n' => $n,
        ];
    }
}
