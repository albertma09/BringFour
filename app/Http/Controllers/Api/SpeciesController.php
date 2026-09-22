<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpeciesController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $formatId = $this->formatId($request);
        $regulationId = DB::table('formats')->where('id', $formatId)->value('regulation_id');
        $busqueda = trim((string) $request->query('q', ''));

        $rows = DB::table('species as s')
            ->join('legality as l', function ($join) use ($regulationId) {
                $join->on('l.entity_id', '=', 's.id')
                    ->where('l.entity_type', 'species')
                    ->where('l.is_legal', true)
                    ->where('l.regulation_id', $regulationId);
            })
            ->where('s.is_buildable', true)
            ->when($busqueda !== '', fn ($q) => $q->where(function ($inner) use ($busqueda) {
                $inner->where('s.name', 'ilike', "%{$busqueda}%")
                    ->orWhere('s.name_es', 'ilike', "%{$busqueda}%");
            }))
            ->orderBy('s.name')
            ->select('s.slug', 's.name', 's.name_es', 's.types', 's.base_stats', 's.sprite_file', 's.sprite_stone_slug', 's.is_mega')
            ->get();

        return response()->json([
            'total' => $rows->count(),
            'especies' => $rows->map(fn ($row) => $this->payload($row))->all(),
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $species = $this->species($slug);
        $formatId = $this->formatId($request);
        $regulationId = DB::table('formats')->where('id', $formatId)->value('regulation_id');

        $legal = DB::table('legality')
            ->where('regulation_id', $regulationId)
            ->where('entity_type', 'species')
            ->where('entity_id', $species->id)
            ->where('is_legal', true)
            ->exists();

        $mega = DB::selectOne(
            'select slug, name, name_es
             from items
             where jsonb_typeof(mega_evolutions) = \'object\'
               and exists (
                   select 1 from jsonb_each_text(mega_evolutions) e
                   where e.key = ? or e.value = ?
               )
             limit 1',
            [$species->slug, $species->slug],
        );

        return response()->json([
            'especie' => $this->payload($species) + [
                'abilities' => json_decode($species->abilities, true),
                'national_dex' => $species->national_dex,
                'weight_kg' => $species->weight_kg,
                'legal' => $legal,
                'megapiedra' => $mega ? ['slug' => $mega->slug, 'name' => $mega->name, 'name_es' => $mega->name_es] : null,
            ],
        ]);
    }

    private function payload(object $row): array
    {
        return [
            'slug' => $row->slug,
            'name' => $row->name,
            'name_es' => $row->name_es,
            'types' => json_decode($row->types, true),
            'base_stats' => json_decode($row->base_stats, true),
            'sprite' => $row->sprite_file,
            'sprite_stone' => $row->sprite_stone_slug,
            'is_mega' => (bool) $row->is_mega,
        ];
    }
}
