<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class TypeUsage
{
    private const TTL = 3600;

    public function shares(int $formatId, int $elo): array
    {
        return Cache::remember("tipos:{$formatId}:{$elo}", self::TTL, function () use ($formatId, $elo) {
            $filas = DB::select('
                select lower(m.type) as tipo, count(*) as n
                from replay_actions a
                join replay_turns rt on rt.id = a.replay_turn_id
                join replays r on r.id = rt.replay_id
                join moves m on m.id = a.move_id
                where m.power > 0 and r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
                group by lower(m.type)
            ', [$formatId, $elo]);

            $total = array_sum(array_map(fn ($f) => (int) $f->n, $filas));

            if ($total === 0) {
                return [];
            }

            $salida = [];

            foreach ($filas as $fila) {
                $salida[$fila->tipo] = round(100 * $fila->n / $total, 2);
            }

            arsort($salida);

            return $salida;
        });
    }
}
