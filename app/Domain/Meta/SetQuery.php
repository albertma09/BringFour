<?php

namespace App\Domain\Meta;

use Illuminate\Support\Facades\DB;

final class SetQuery
{
    private const FAMILIA = 'familia as (
        select id from species where id = ? or base_form_id = ?
    )';

    private const TRAIDAS = 'traidas as (
        select t.replay_id, t.side
        from replay_teams t
        join replays r on r.id = t.replay_id
        where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
          and t.brought and t.species_id in (select id from familia)
        group by t.replay_id, t.side
    )';

    public function forSpecies(int $speciesId, int $formatId, int $elo, int $min, int $top): array
    {
        $traidas = $this->traidas($speciesId, $formatId, $elo);

        if ($traidas === 0) {
            return $this->vacio(0);
        }

        return [
            'traidas' => $traidas,
            'movimientos' => $this->movimientos($speciesId, $formatId, $elo, $min, $top),
            'habilidades' => $this->revelaciones($speciesId, $formatId, $elo, 'ability', $min),
            'objetos' => $this->revelaciones($speciesId, $formatId, $elo, 'item', $min, $top),
        ];
    }

    private function traidas(int $speciesId, int $formatId, int $elo): int
    {
        $sql = 'with '.self::FAMILIA.', '.self::TRAIDAS.' select count(*) as n from traidas';

        return (int) DB::select($sql, [$speciesId, $speciesId, $formatId, $elo])[0]->n;
    }

    private function movimientos(int $speciesId, int $formatId, int $elo, int $min, int $top): array
    {
        $sql = 'with '.self::FAMILIA.",
            acciones as (
                select rt.replay_id, a.side, a.move_id
                from replay_actions a
                join replay_turns rt on rt.id = a.replay_turn_id
                join replays r on r.id = rt.replay_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
                  and a.move_id is not null
                  and a.actor_species_id in (select id from familia)
            ),
            activas as (
                select replay_id, side from acciones group by replay_id, side
            ),
            usos as (
                select move_id, count(distinct (replay_id, side)) as n
                from acciones
                group by move_id
            )
            select m.slug, m.name, m.name_es, m.type, m.category, m.power,
                   u.n,
                   (select count(*) from activas) as base
            from usos u
            join moves m on m.id = u.move_id
            where u.n >= ?
            order by u.n desc
            limit ?";

        $filas = DB::select($sql, [$speciesId, $speciesId, $formatId, $elo, $min, $top]);

        return array_map(fn ($fila) => [
            'slug' => $fila->slug,
            'name' => $fila->name,
            'name_es' => $fila->name_es,
            'type' => $fila->type,
            'category' => $fila->category,
            'power' => $fila->power,
            'n' => (int) $fila->n,
            'base' => (int) $fila->base,
            'pct' => round(100 * $fila->n / max(1, (int) $fila->base), 1),
        ], $filas);
    }

    private function revelaciones(int $speciesId, int $formatId, int $elo, string $kind, int $min, int $top = 6): array
    {
        $tabla = $kind === 'ability' ? 'abilities' : 'items';
        $columna = $kind === 'ability' ? 'ability_id' : 'item_id';

        $sql = 'with '.self::FAMILIA.', '.self::TRAIDAS.",
            vistas as (
                select v.{$columna} as valor_id, count(*) as n
                from replay_reveals v
                join traidas t on t.replay_id = v.replay_id and t.side = v.side
                where v.kind = ? and v.species_id in (select id from familia)
                group by v.{$columna}
            )
            select e.slug, e.name, e.name_es, w.n,
                   (select coalesce(sum(n), 0) from vistas) as reveladas,
                   (select count(*) from traidas) as traidas
            from vistas w
            join {$tabla} e on e.id = w.valor_id
            where w.n >= ?
            order by w.n desc
            limit ?";

        $filas = DB::select($sql, [$speciesId, $speciesId, $formatId, $elo, $kind, $min, $top]);

        return array_map(fn ($fila) => [
            'slug' => $fila->slug,
            'name' => $fila->name,
            'name_es' => $fila->name_es,
            'n' => (int) $fila->n,
            'reveladas' => (int) $fila->reveladas,
            'traidas' => (int) $fila->traidas,
            'pct' => round(100 * $fila->n / max(1, (int) $fila->reveladas), 1),
        ], $filas);
    }

    private function vacio(int $traidas): array
    {
        return ['traidas' => $traidas, 'movimientos' => [], 'habilidades' => [], 'objetos' => []];
    }
}
