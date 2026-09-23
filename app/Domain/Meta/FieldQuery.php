<?php

namespace App\Domain\Meta;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class FieldQuery
{
    private const TTL = 3600;

    public function shares(int $formatId, int $elo): array
    {
        return Cache::remember("campos:{$formatId}:{$elo}", self::TTL, function () use ($formatId, $elo) {
            $filas = DB::select("
                select t.field_state->>'clima' as clima,
                       t.field_state->>'terreno' as terreno,
                       count(*) as turnos,
                       count(distinct t.replay_id) as partidas
                from replay_turns t
                join replays r on r.id = t.replay_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
                group by 1, 2
            ", [$formatId, $elo]);

            $total = DB::selectOne('
                select count(distinct t.replay_id) as n
                from replay_turns t
                join replays r on r.id = t.replay_id
                where r.format_id = ? and coalesce(r.elo_bucket, 0) >= ?
            ', [$formatId, $elo]);

            $partidas = max(1, (int) $total->n);
            $campos = [];

            foreach ($filas as $fila) {
                foreach (['clima' => $fila->clima, 'terreno' => $fila->terreno] as $clase => $valor) {
                    if ($valor === null) {
                        continue;
                    }

                    $campos[$valor] ??= ['campo' => $valor, 'clase' => $clase, 'turnos' => 0, 'partidas' => 0];
                    $campos[$valor]['turnos'] += (int) $fila->turnos;
                    $campos[$valor]['partidas'] += (int) $fila->partidas;
                }
            }

            foreach ($campos as $clave => $campo) {
                $campos[$clave]['pct'] = round(100 * min($campo['partidas'], $partidas) / $partidas, 1);
            }

            uasort($campos, fn (array $a, array $b) => $b['turnos'] <=> $a['turnos']);

            return ['partidas' => $partidas, 'campos' => array_values($campos)];
        });
    }
}
