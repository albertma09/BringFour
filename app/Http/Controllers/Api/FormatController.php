<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FormatController extends ApiController
{
    public function index(): JsonResponse
    {
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
            ])->all(),
        ]);
    }
}
