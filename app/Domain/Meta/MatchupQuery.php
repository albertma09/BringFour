<?php

namespace App\Domain\Meta;

use App\Domain\Stats\Proportion;
use Illuminate\Support\Facades\DB;

final class MatchupQuery
{
    public function universe(int $formatId, int $elo, int $rivalId): array
    {
        $row = DB::selectOne(
            'with '.SideQuery::SIDES.', '.SideQuery::COMPLETE.', '.SideQuery::VERSUS.'
            select count(*) as completos, count(v.side) as enfrentados
            from completos c
            left join versus v on v.replay_id = c.replay_id and v.side = c.side',
            [$formatId, $elo, $rivalId],
        );

        return [
            'completos' => (int) $row->completos,
            'enfrentados' => (int) $row->enfrentados,
        ];
    }

    public function rows(int $formatId, int $elo, int $rivalId, int $min): array
    {
        $rows = DB::select(
            'with '.SideQuery::SIDES.', '.SideQuery::COMPLETE.', '.SideQuery::VERSUS.'
            select s.slug,
                   s.name,
                   s.name_es,
                   count(*) filter (where v.side is not null) as n,
                   count(*) filter (where v.side is not null and t.brought) as traido,
                   count(*) filter (where v.side is null) as n_sin,
                   count(*) filter (where v.side is null and t.brought) as traido_sin
            from completos c
            join replay_teams t on t.replay_id = c.replay_id and t.side = c.side
            join species s on s.id = t.species_id
            left join versus v on v.replay_id = c.replay_id and v.side = c.side
            group by s.slug, s.name, s.name_es',
            [$formatId, $elo, $rivalId],
        );

        $comparadas = array_map(fn ($row) => $this->compare($row), $rows);

        $publicables = array_values(array_filter(
            $comparadas,
            fn (array $fila) => $fila['n'] >= $min && $fila['n_sin'] >= $min,
        ));

        usort(
            $publicables,
            fn (array $a, array $b) => [$b['significativo'], abs($b['delta'])] <=> [$a['significativo'], abs($a['delta'])],
        );

        return [
            'filas' => $publicables,
            'ocultas' => count($comparadas) - count($publicables),
        ];
    }

    private function compare(object $row): array
    {
        $n = (int) $row->n;
        $nSin = (int) $row->n_sin;
        $traido = (int) $row->traido;
        $traidoSin = (int) $row->traido_sin;

        $pct = $n > 0 ? $traido / $n : 0.0;
        $pctSin = $nSin > 0 ? $traidoSin / $nSin : 0.0;

        return [
            'slug' => $row->slug,
            'name' => $row->name,
            'name_es' => $row->name_es,
            'n' => $n,
            'n_sin' => $nSin,
            'bring_pct' => round($pct * 100, 1),
            'bring_pct_sin' => round($pctSin * 100, 1),
            'delta' => round(($pct - $pctSin) * 100, 1),
            'significativo' => Proportion::differsFrom($traido, $n, $pctSin),
        ];
    }
}
