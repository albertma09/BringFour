<?php

namespace App\Domain\Meta;

use App\Domain\Stats\Proportion;
use Illuminate\Support\Facades\DB;

final class PartnerQuery
{
    public function forSlugs(int $formatId, int $elo, array $slugs, int $min, int $top): array
    {
        $slugs = array_values(array_unique(array_filter($slugs)));

        if ($slugs === []) {
            return ['n' => 0, 'equipos' => 0, 'companeros' => []];
        }

        $marcas = implode(', ', array_fill(0, count($slugs), '?'));

        $sql = "
            with lados as (
                select t.replay_id, t.side, t.species_id
                from replay_teams t
                join replays r on r.id = t.replay_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
            ),
            equipos as (
                select replay_id, side from lados group by replay_id, side
            ),
            elegidos as (
                select id from species where slug in ({$marcas})
            ),
            base as (
                select l.species_id, count(*) as n
                from lados l
                group by l.species_id
            ),
            conjunto as (
                select l.replay_id, l.side
                from lados l
                where l.species_id in (select id from elegidos)
                group by l.replay_id, l.side
                having count(distinct l.species_id) = (select count(*) from elegidos)
            ),
            juntos as (
                select l.species_id, count(*) as n
                from conjunto c
                join lados l on l.replay_id = c.replay_id and l.side = c.side
                where l.species_id not in (select id from elegidos)
                group by l.species_id
            )
            select s.slug, s.name, s.name_es, s.sprite_file, s.sprite_stone_slug, s.types,
                   j.n as juntos,
                   b.n as base,
                   (select count(*) from conjunto) as con_todos,
                   (select count(*) from equipos) as total
            from juntos j
            join species s on s.id = j.species_id
            join base b on b.species_id = j.species_id
            where j.n >= ?
            order by j.n desc
        ";

        $filas = DB::select($sql, [$formatId, $elo, ...$slugs, $min]);

        if ($filas === []) {
            return ['n' => 0, 'equipos' => 0, 'companeros' => []];
        }

        $conTodos = (int) $filas[0]->con_todos;
        $total = (int) $filas[0]->total;

        $companeros = array_map(
            fn ($fila) => $this->companero($fila, $conTodos, $total),
            $filas,
        );

        usort($companeros, fn ($a, $b) => $b['afinidad'] <=> $a['afinidad']);

        return [
            'n' => $conTodos,
            'equipos' => $total,
            'companeros' => array_slice($companeros, 0, $top),
        ];
    }

    private function companero(object $fila, int $conTodos, int $total): array
    {
        $juntos = (int) $fila->juntos;
        $condicional = $conTodos > 0 ? $juntos / $conTodos : 0.0;
        $general = $total > 0 ? ((int) $fila->base) / $total : 0.0;
        [$suelo] = Proportion::wilson($juntos, $conTodos);

        return [
            'slug' => $fila->slug,
            'name' => $fila->name,
            'name_es' => $fila->name_es,
            'sprite' => $fila->sprite_file,
            'sprite_stone' => $fila->sprite_stone_slug,
            'types' => json_decode((string) $fila->types, true),
            'n' => $juntos,
            'juntos_pct' => round(100 * $condicional, 1),
            'general_pct' => round(100 * $general, 1),
            'veces' => $general > 0 ? round($condicional / $general, 2) : null,
            'afinidad' => $general > 0 ? round($suelo / $general, 4) : 0.0,
        ];
    }
}
