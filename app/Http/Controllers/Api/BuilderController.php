<?php

namespace App\Http\Controllers\Api;

use App\Domain\Build\MetaRoster;
use App\Domain\Build\SlotDefaults;
use App\Domain\Build\SpeedContext;
use App\Domain\Meta\PartnerQuery;
use App\Domain\Meta\SetQuery;
use App\Domain\Stats\SpreadAdvisor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuilderController extends ApiController
{
    public function alignments(): JsonResponse
    {
        $rows = DB::table('alignments')->orderBy('name')->get();

        return response()->json([
            'alineamientos' => $rows->map(fn ($fila) => [
                'slug' => $fila->slug,
                'name' => $fila->name,
                'plus' => $fila->plus_stat,
                'minus' => $fila->minus_stat,
                'neutral' => $fila->plus_stat === null || $fila->plus_stat === $fila->minus_stat,
            ])->all(),
        ]);
    }

    public function options(Request $request, string $slug): JsonResponse
    {
        $species = $this->species($slug);
        $formatId = $this->formatId($request);
        $regulationId = DB::table('formats')->where('id', $formatId)->value('regulation_id');

        $moves = DB::table('learnsets as l')
            ->join('moves as m', 'm.id', '=', 'l.move_id')
            ->where('l.regulation_id', $regulationId)
            ->where('l.species_id', $species->id)
            ->orderBy('m.name')
            ->get(['m.slug', 'm.name', 'm.name_es', 'm.type', 'm.category', 'm.power', 'm.accuracy', 'm.pp', 'm.priority', 'm.target', 'm.description']);

        $items = DB::table('items as i')
            ->join('legality as l', function ($join) use ($regulationId) {
                $join->on('l.entity_id', '=', 'i.id')
                    ->where('l.entity_type', 'items')
                    ->where('l.is_legal', true)
                    ->where('l.regulation_id', $regulationId);
            })
            ->orderBy('i.name')
            ->get(['i.slug', 'i.name', 'i.name_es', 'i.is_mega_stone', 'i.mega_evolutions']);

        return response()->json([
            'especie' => [
                'slug' => $species->slug,
                'name' => $species->name,
                'name_es' => $species->name_es,
                'types' => json_decode($species->types, true),
                'base_stats' => json_decode($species->base_stats, true),
                'abilities' => $this->habilidades($species),
                'sprite' => $species->sprite_file,
                'sprite_stone' => $species->sprite_stone_slug,
            ],
            'movimientos' => $moves->map(fn ($fila) => [
                'slug' => $fila->slug,
                'name' => $fila->name,
                'name_es' => $fila->name_es,
                'type' => $fila->type,
                'category' => $fila->category,
                'power' => $fila->power,
                'accuracy' => $fila->accuracy,
                'pp' => $fila->pp,
                'priority' => $fila->priority,
                'target' => $fila->target,
                'description' => $fila->description,
            ])->all(),
            'objetos' => $items->map(fn ($fila) => [
                'slug' => $fila->slug,
                'name' => $fila->name,
                'name_es' => $fila->name_es,
                'mega' => $this->megaDe($fila, $species->slug),
            ])->all(),
        ]);
    }

    public function partners(Request $request, PartnerQuery $query): JsonResponse
    {
        $slugs = array_slice(array_filter(explode(',', (string) $request->query('species', ''))), 0, 5);

        foreach ($slugs as $slug) {
            $this->species($slug);
        }

        $resultado = $query->forSlugs(
            $this->formatId($request),
            $this->elo($request),
            $slugs,
            $this->min($request),
            $this->top($request, 8, 24),
        );

        return response()->json($resultado + [
            'elegidos' => $slugs,
            'muestra' => ['min_sample' => $this->min($request)],
        ]);
    }

    public function set(Request $request, SetQuery $query, string $slug): JsonResponse
    {
        $species = $this->species($slug);

        $resultado = $query->forSpecies(
            (int) $species->id,
            $this->formatId($request),
            $this->elo($request),
            $this->min($request),
            $this->top($request, 8, 24),
        );

        return response()->json($resultado + [
            'especie' => $species->slug,
            'muestra' => ['min_sample' => $this->min($request)],
        ]);
    }

    public function defaults(Request $request, SlotDefaults $defaults, MetaRoster $roster, string $slug): JsonResponse
    {
        $species = $this->species($slug);
        $formatId = $this->formatId($request);
        $elo = $this->elo($request);
        $regulationId = (int) DB::table('formats')->where('id', $formatId)->value('regulation_id');

        return response()->json(
            $defaults->forSpecies($species, $formatId, $elo, $regulationId, $roster->brought($formatId, $elo)),
        );
    }

    public function spread(
        Request $request,
        SpreadAdvisor $advisor,
        SpeedContext $speed,
        MetaRoster $roster,
        string $slug,
    ): JsonResponse {
        $species = $this->species($slug);
        $alineamiento = (string) $request->query('alignment', '');
        $fila = $alineamiento === ''
            ? null
            : DB::table('alignments')->where('slug', $alineamiento)->first();

        $baseStats = json_decode((string) $species->base_stats, true) ?: [];
        $meta = $roster->brought($this->formatId($request), $this->elo($request));
        $ritmo = $meta === [] ? null : $speed->forSpecies($baseStats, $meta);

        $reparto = $advisor->suggest(
            $baseStats,
            $fila->plus_stat ?? null,
            $fila->minus_stat ?? null,
            $ritmo['ritmo'] ?? null,
        );

        return response()->json($reparto + [
            'especie' => $species->slug,
            'alineamiento' => $fila->slug ?? null,
            'tope' => SpreadAdvisor::TOPE,
            'velocidad' => $ritmo,
        ]);
    }

    private function megaDe(object $item, string $slug): ?string
    {
        if (! $item->is_mega_stone) {
            return null;
        }

        $mapa = json_decode((string) $item->mega_evolutions, true);

        return is_array($mapa) ? ($mapa[$slug] ?? null) : null;
    }
}
