<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FormatController extends ApiController
{
    private const CORTES = [0, 1500, 1630, 1760];

    public function index(): JsonResponse
    {
        $cortes = $this->cortes();

        $rows = DB::table('formats as f')
            ->join('regulations as r', 'r.id', '=', 'f.regulation_id')
            ->where('f.process_replays', true)
            ->orderByDesc('r.is_active')
            ->orderByRaw('r.starts_at desc nulls last')
            ->orderBy('f.slug')
            ->select('f.showdown_id', 'f.slug', 'f.battle_type', 'f.bring_count', 'r.code as regulacion', 'r.starts_at', 'r.ends_at')
            ->get();

        return response()->json([
            'formatos' => $rows->map(fn ($row) => [
                'showdown_id' => $row->showdown_id,
                'slug' => $row->slug,
                'battle_type' => $row->battle_type,
                'bring_count' => $row->bring_count,
                'regulacion' => $row->regulacion,
                'desde' => $row->starts_at,
                'hasta' => $row->ends_at,
                'cortes' => $cortes[$row->showdown_id] ?? array_fill_keys(self::CORTES, 0),
            ])->all(),
        ]);
    }

    private function cortes(): array
    {
        $campos = array_map(
            fn (int $corte) => "count(r.id) filter (where coalesce(r.elo_bucket, 0) >= {$corte}) as c{$corte}",
            self::CORTES,
        );

        $filas = DB::select('
            select f.showdown_id, '.implode(', ', $campos).'
            from formats f
            left join replays r on r.format_id = f.id
            group by f.showdown_id
        ');

        $salida = [];

        foreach ($filas as $fila) {
            foreach (self::CORTES as $corte) {
                $salida[$fila->showdown_id][$corte] = (int) $fila->{'c'.$corte};
            }
        }

        return $salida;
    }
}
