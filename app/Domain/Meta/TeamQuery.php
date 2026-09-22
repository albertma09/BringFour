<?php

namespace App\Domain\Meta;

use Illuminate\Support\Facades\DB;

final class TeamQuery
{
    public const TAMANOS = [2, 3, 4];

    public function cores(int $formatId, int $elo, int $tamano, int $min, int $top): array
    {
        if (! in_array($tamano, self::TAMANOS, true)) {
            return [];
        }

        $alias = range('a', chr(96 + $tamano));
        $joins = [];
        $campos = [];
        $grupos = [];

        foreach ($alias as $indice => $letra) {
            $campos[] = "{$letra}.slug as slug_{$letra}";
            $campos[] = "{$letra}.name as name_{$letra}";
            $campos[] = "{$letra}.name_es as name_es_{$letra}";
            $campos[] = "{$letra}.sprite_file as sprite_{$letra}";
            $campos[] = "{$letra}.sprite_stone_slug as stone_{$letra}";
            $grupos[] = (string) ($indice * 5 + 1);
            $grupos[] = (string) ($indice * 5 + 2);
            $grupos[] = (string) ($indice * 5 + 3);
            $grupos[] = (string) ($indice * 5 + 4);
            $grupos[] = (string) ($indice * 5 + 5);

            if ($indice === 0) {
                continue;
            }

            $anterior = $alias[$indice - 1];
            $joins[] = "join lados {$letra} on {$letra}.replay_id = a.replay_id and {$letra}.side = a.side and {$letra}.slug > {$anterior}.slug";
        }

        $sql = '
            with lados as (
                select t.replay_id, t.side, s.slug, s.name, s.name_es, s.sprite_file, s.sprite_stone_slug
                from replay_teams t
                join replays r on r.id = t.replay_id
                join species s on s.id = t.species_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
            )
            select '.implode(', ', $campos).', count(*) as n
            from lados a
            '.implode("\n            ", $joins).'
            group by '.implode(', ', $grupos).'
            having count(*) >= ?
            order by count(*) desc
            limit ?';

        return array_map(
            fn ($fila) => $this->core($fila, $alias),
            DB::select($sql, [$formatId, $elo, $min, $top]),
        );
    }

    public function teams(int $formatId, int $elo, int $min, int $top): array
    {
        $rows = DB::select('
            with equipos as (
                select t.replay_id,
                       t.side,
                       array_agg(s.slug order by s.slug) as miembros
                from replay_teams t
                join replays r on r.id = t.replay_id
                join species s on s.id = t.species_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
                group by t.replay_id, t.side
            )
            select miembros, count(*) as n
            from equipos
            group by miembros
            having count(*) >= ?
            order by count(*) desc
            limit ?
        ', [$formatId, $elo, $min, $top]);

        if ($rows === []) {
            return [];
        }

        $slugs = [];

        foreach ($rows as $fila) {
            foreach ($this->fromArray($fila->miembros) as $slug) {
                $slugs[$slug] = true;
            }
        }

        $especies = $this->species(array_keys($slugs));

        return array_map(fn ($fila) => [
            'id' => $this->idDe($this->fromArray($fila->miembros)),
            'n' => (int) $fila->n,
            'miembros' => array_values(array_filter(array_map(
                fn (string $slug) => $especies[$slug] ?? null,
                $this->fromArray($fila->miembros),
            ))),
        ], $rows);
    }

    public function team(int $formatId, int $elo, array $slugs): ?array
    {
        sort($slugs);

        if (count($slugs) !== 6) {
            return null;
        }

        $fila = DB::selectOne('
            with equipos as (
                select t.replay_id,
                       t.side,
                       array_agg(s.slug order by s.slug) as miembros
                from replay_teams t
                join replays r on r.id = t.replay_id
                join species s on s.id = t.species_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
                group by t.replay_id, t.side
            ),
            elegidos as (
                select replay_id, side from equipos where miembros = ?
            )
            select count(*) as n,
                   count(*) filter (where completo) as completos
            from (
                select e.replay_id,
                       e.side,
                       (case e.side when \'p1\' then r.p1_team_size else r.p2_team_size end)
                           = count(*) filter (where t.brought) as completo
                from elegidos e
                join replays r on r.id = e.replay_id
                join replay_teams t on t.replay_id = e.replay_id and t.side = e.side
                group by e.replay_id, e.side, r.p1_team_size, r.p2_team_size
            ) z
        ', [$formatId, $elo, '{'.implode(',', $slugs).'}']);

        if (! $fila || (int) $fila->n === 0) {
            return null;
        }

        $miembros = DB::select('
            with equipos as (
                select t.replay_id,
                       t.side,
                       array_agg(s.slug order by s.slug) as miembros
                from replay_teams t
                join replays r on r.id = t.replay_id
                join species s on s.id = t.species_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
                group by t.replay_id, t.side
            ),
            elegidos as (
                select replay_id, side from equipos where miembros = ?
            ),
            completos as (
                select e.replay_id, e.side
                from elegidos e
                join replays r on r.id = e.replay_id
                join replay_teams t on t.replay_id = e.replay_id and t.side = e.side
                group by e.replay_id, e.side, r.p1_team_size, r.p2_team_size
                having (case e.side when \'p1\' then r.p1_team_size else r.p2_team_size end)
                    = count(*) filter (where t.brought)
            )
            select s.slug, s.name, s.name_es, s.sprite_file, s.sprite_stone_slug, s.types,
                   count(*) as n,
                   count(*) filter (where t.brought) as traido,
                   count(*) filter (where t.lead) as lead
            from completos c
            join replay_teams t on t.replay_id = c.replay_id and t.side = c.side
            join species s on s.id = t.species_id
            group by s.slug, s.name, s.name_es, s.sprite_file, s.sprite_stone_slug, s.types
            order by count(*) filter (where t.brought) desc
        ', [$formatId, $elo, '{'.implode(',', $slugs).'}']);

        return [
            'id' => $this->idDe($slugs),
            'n' => (int) $fila->n,
            'completos' => (int) $fila->completos,
            'miembros' => array_map(fn ($m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                'name_es' => $m->name_es,
                'sprite' => $m->sprite_file,
                'sprite_stone' => $m->sprite_stone_slug,
                'types' => json_decode($m->types, true),
                'n' => (int) $m->n,
                'bring_pct' => $m->n > 0 ? round(100 * $m->traido / $m->n, 1) : 0.0,
                'lead_pct' => $m->n > 0 ? round(100 * $m->lead / $m->n, 1) : 0.0,
            ], $miembros),
        ];
    }

    private function core(object $fila, array $alias): array
    {
        $miembros = [];

        foreach ($alias as $letra) {
            $miembros[] = [
                'slug' => $fila->{"slug_{$letra}"},
                'name' => $fila->{"name_{$letra}"},
                'name_es' => $fila->{"name_es_{$letra}"},
                'sprite' => $fila->{"sprite_{$letra}"},
                'sprite_stone' => $fila->{"stone_{$letra}"},
            ];
        }

        return ['n' => (int) $fila->n, 'miembros' => $miembros];
    }

    private function species(array $slugs): array
    {
        return DB::table('species')
            ->whereIn('slug', $slugs)
            ->get(['slug', 'name', 'name_es', 'sprite_file', 'sprite_stone_slug', 'types'])
            ->keyBy('slug')
            ->map(fn ($fila) => [
                'slug' => $fila->slug,
                'name' => $fila->name,
                'name_es' => $fila->name_es,
                'sprite' => $fila->sprite_file,
                'sprite_stone' => $fila->sprite_stone_slug,
                'types' => json_decode($fila->types, true),
            ])
            ->all();
    }

    private function fromArray(string $valor): array
    {
        return array_values(array_filter(explode(',', trim($valor, '{}'))));
    }

    private function idDe(array $slugs): string
    {
        sort($slugs);

        return implode('-', $slugs);
    }
}
