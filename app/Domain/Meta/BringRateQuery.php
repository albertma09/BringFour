<?php

namespace App\Domain\Meta;

use Illuminate\Support\Facades\DB;

final class BringRateQuery
{
    public function universe(int $formatId, int $elo): array
    {
        $row = DB::selectOne(
            'with '.SideQuery::SIDES.'
            select count(*) as total,
                   count(*) filter (where declarado is not null and visto = declarado) as completos
            from lados',
            [$formatId, $elo],
        );

        return [
            'total' => (int) $row->total,
            'completos' => (int) $row->completos,
            'descartados' => (int) $row->total - (int) $row->completos,
        ];
    }

    public function rows(int $formatId, int $elo, int $min, int $top): array
    {
        $rows = DB::select(
            'with '.SideQuery::SIDES.', '.SideQuery::COMPLETE.'
            select s.slug,
                   s.name,
                   s.name_es,
                   s.types,
                   count(*) as n,
                   count(*) filter (where t.brought) as traido,
                   count(*) filter (where t.lead) as lead
            from completos c
            join replay_teams t on t.replay_id = c.replay_id and t.side = c.side
            join species s on s.id = t.species_id
            group by s.slug, s.name, s.name_es, s.types
            having count(*) >= ?
            order by count(*) desc
            limit ?',
            [$formatId, $elo, $min, $top],
        );

        return array_map(fn ($row) => [
            'slug' => $row->slug,
            'name' => $row->name,
            'name_es' => $row->name_es,
            'types' => json_decode($row->types, true),
            'n' => (int) $row->n,
            'traido' => (int) $row->traido,
            'bring_pct' => round(100 * $row->traido / $row->n, 1),
            'lead_pct' => round(100 * $row->lead / $row->n, 1),
        ], $rows);
    }
}
