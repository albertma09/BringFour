<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class MetaRoster
{
    public function brought(int $formatId, int $elo, int $min = 30): array
    {
        $filas = DB::select('
            select s.slug, s.name, s.name_es, s.types, s.base_stats, s.sprite_file, s.sprite_stone_slug,
                   count(*) filter (where t.brought) as traido
            from replay_teams t
            join replays r on r.id = t.replay_id
            join species s on s.id = t.species_id
            where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
            group by s.slug, s.name, s.name_es, s.types, s.base_stats, s.sprite_file, s.sprite_stone_slug
            having count(*) filter (where t.brought) >= ?
            order by traido desc
        ', [$formatId, $elo, $min]);

        return array_map(fn ($fila) => [
            'slug' => $fila->slug,
            'name' => $fila->name,
            'name_es' => $fila->name_es,
            'sprite' => $fila->sprite_file,
            'sprite_stone' => $fila->sprite_stone_slug,
            'tipos' => json_decode((string) $fila->types, true) ?: [],
            'stats' => json_decode((string) $fila->base_stats, true) ?: [],
            'peso' => (int) $fila->traido,
        ], $filas);
    }
}
