<?php

namespace App\Domain\Meta;

use App\Console\Commands\BehaviorBuild;
use App\Domain\Stats\Proportion;
use Illuminate\Support\Facades\DB;

final class BehaviorQuery
{
    public const FASES = ['apertura', 'medio'];

    public function contexts(int $formatId, string $actorSlug, int $elo, int $top): array
    {
        $rows = DB::select('
            select p.context->>\'rival\' as rival,
                   p.context->>\'fase\' as fase,
                   sum(p.n) as total,
                   s.name as rival_name,
                   s.name_es as rival_name_es
            from behavior_priors p
            join species s on s.slug = p.context->>\'rival\'
            where p.format_id = ? and p.prior_type = ? and p.elo_bucket = ? and p.context->>\'actor\' = ?
            group by 1, 2, 4, 5
            order by sum(p.n) desc
            limit ?
        ', [$formatId, BehaviorBuild::PRIOR_TYPE, $elo, $actorSlug, $top]);

        return array_map(fn ($row) => [
            'rival' => $row->rival,
            'rival_name' => $row->rival_name,
            'rival_name_es' => $row->rival_name_es,
            'fase' => $row->fase,
            'n' => (int) $row->total,
        ], $rows);
    }

    public function distribution(int $formatId, string $actorSlug, string $rivalSlug, string $fase, int $elo, int $top): array
    {
        $rows = DB::select('
            select p.action_key, p.n, m.name as move_name, m.name_es as move_name_es, m.type as move_type
            from behavior_priors p
            left join moves m on \'move:\' || m.slug = p.action_key
            where p.format_id = ? and p.prior_type = ? and p.elo_bucket = ?
              and p.context->>\'actor\' = ? and p.context->>\'rival\' = ? and p.context->>\'fase\' = ?
            order by p.n desc
            limit ?
        ', [$formatId, BehaviorBuild::PRIOR_TYPE, $elo, $actorSlug, $rivalSlug, $fase, $top]);

        $total = (int) DB::table('behavior_priors')
            ->where('format_id', $formatId)
            ->where('prior_type', BehaviorBuild::PRIOR_TYPE)
            ->where('elo_bucket', $elo)
            ->whereRaw('context->>\'actor\' = ?', [$actorSlug])
            ->whereRaw('context->>\'rival\' = ?', [$rivalSlug])
            ->whereRaw('context->>\'fase\' = ?', [$fase])
            ->sum('n');

        return [
            'n' => $total,
            'filas' => array_map(function ($row) use ($total) {
                [$low, $high] = Proportion::wilson((int) $row->n, $total);

                return [
                    'action_key' => $row->action_key,
                    'tipo' => $row->action_key === 'switch' ? 'switch' : 'move',
                    'move_name' => $row->move_name,
                    'move_name_es' => $row->move_name_es,
                    'move_type' => $row->move_type,
                    'n' => (int) $row->n,
                    'pct' => $total > 0 ? round(100 * $row->n / $total, 1) : 0.0,
                    'intervalo' => [round($low * 100, 1), round($high * 100, 1)],
                ];
            }, $rows),
        ];
    }
}
