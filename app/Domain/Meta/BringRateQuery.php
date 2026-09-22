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
        $universo = $this->universe($formatId, $elo);

        $rows = DB::select(
            'with '.SideQuery::SIDES.', '.SideQuery::COMPLETE.'
            select s.slug,
                   s.name,
                   s.name_es,
                   s.types,
                   s.sprite_file,
                   s.sprite_stone_slug,
                   count(*) as llevado,
                   count(*) filter (where c.side is not null) as n,
                   count(*) filter (where c.side is not null and t.brought) as traido,
                   count(*) filter (where c.side is not null and t.lead) as lead
            from replay_teams t
            join replays r on r.id = t.replay_id and r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
            join species s on s.id = t.species_id
            left join completos c on c.replay_id = t.replay_id and c.side = t.side
            group by s.slug, s.name, s.name_es, s.types, s.sprite_file, s.sprite_stone_slug
            having count(*) filter (where c.side is not null) >= ?
            order by count(*) desc
            limit ?',
            [$formatId, $elo, $formatId, $elo, $min, $top],
        );

        return array_map(fn ($row) => [
            'slug' => $row->slug,
            'name' => $row->name,
            'name_es' => $row->name_es,
            'types' => json_decode($row->types, true),
            'sprite' => $row->sprite_file,
            'sprite_stone' => $row->sprite_stone_slug,
            'llevado' => (int) $row->llevado,
            'usage_pct' => $universo['total'] > 0 ? round(100 * $row->llevado / $universo['total'], 1) : 0.0,
            'n' => (int) $row->n,
            'traido' => (int) $row->traido,
            'bring_pct' => $row->n > 0 ? round(100 * $row->traido / $row->n, 1) : 0.0,
            'lead_pct' => $row->n > 0 ? round(100 * $row->lead / $row->n, 1) : 0.0,
        ], $rows);
    }
}
