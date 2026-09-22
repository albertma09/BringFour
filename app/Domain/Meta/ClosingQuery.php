<?php

namespace App\Domain\Meta;

use App\Domain\Stats\Proportion;
use Illuminate\Support\Facades\DB;

final class ClosingQuery
{
    public function rates(int $formatId, int $elo, int $min): array
    {
        $filas = DB::select('
            with ganadas as (
                select t.replay_id, t.side, s.slug
                from replay_teams t
                join replays r on r.id = t.replay_id
                join species s on s.id = t.species_id
                where t.brought and r.winner_side = t.side
                  and r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
            ),
            ultimo as (
                select distinct on (replay_id) replay_id, field_state
                from replay_turns
                order by replay_id, turn_no desc
            ),
            en_pie as (
                select u.replay_id,
                       substring(hueco from 1 for 2) as lado,
                       coalesce(case when s.is_mega then b.slug end, s.slug, dato->>\'especie\') as slug
                from ultimo u
                cross join jsonb_each(u.field_state->\'activos\') as e(hueco, dato)
                left join species s on s.slug = dato->>\'especie\'
                left join species b on b.id = s.base_form_id
            )
            select g.slug,
                   count(*) as ganadas,
                   count(*) filter (where exists (
                       select 1 from en_pie p
                       where p.replay_id = g.replay_id and p.lado = g.side and p.slug = g.slug
                   )) as cierra
            from ganadas g
            group by g.slug
            having count(*) >= ?
        ', [$formatId, $elo, $min]);

        $salida = [];

        foreach ($filas as $fila) {
            $ganadas = (int) $fila->ganadas;
            $cierra = (int) $fila->cierra;
            [$suelo, $techo] = Proportion::wilson($cierra, $ganadas);

            $salida[$fila->slug] = [
                'ganadas' => $ganadas,
                'cierra' => $cierra,
                'pct' => round(100 * $cierra / $ganadas, 1),
                'intervalo' => [round(100 * $suelo, 1), round(100 * $techo, 1)],
            ];
        }

        return $salida;
    }

    public function forSpecies(string $slug, int $formatId, int $elo, int $min): ?array
    {
        return $this->rates($formatId, $elo, $min)[$slug] ?? null;
    }
}
