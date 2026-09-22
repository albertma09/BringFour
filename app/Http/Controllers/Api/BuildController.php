<?php

namespace App\Http\Controllers\Api;

use App\Domain\Build\MetaRoster;
use App\Domain\Build\RoleClassifier;
use App\Domain\Build\SetBuilder;
use App\Domain\Build\SpeedContext;
use App\Domain\Build\StructuralPartnerQuery;
use App\Domain\Build\ThreatQuery;
use App\Domain\Meta\ClosingQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildController extends ApiController
{
    public function analysis(
        Request $request,
        SetBuilder $builder,
        SpeedContext $speed,
        MetaRoster $roster,
        RoleClassifier $roles,
        ClosingQuery $cierre,
        string $slug,
    ): JsonResponse {
        $species = $this->species($slug);
        [$formatId, $regulationId] = $this->contexto($request);
        $elo = $this->elo($request);
        $meta = $roster->brought($formatId, $elo);

        $conjunto = $builder->build($species, $regulationId, $meta);
        $ritmo = $speed->forSpecies(json_decode((string) $species->base_stats, true) ?: [], $meta);

        return response()->json([
            'especie' => $this->ficha($species),
            'velocidad' => $ritmo,
            'papel' => $roles->forSpecies($species, $regulationId),
            'cierre' => $cierre->forSpecies($species->slug, $formatId, $elo, $this->min($request)),
            'conjunto' => $conjunto,
            'meta' => ['especies' => count($meta), 'traidas' => array_sum(array_column($meta, 'peso'))],
            'deducido' => true,
        ]);
    }

    public function threats(Request $request, ThreatQuery $query, MetaRoster $roster, string $slug): JsonResponse
    {
        $species = $this->species($slug);
        [$formatId, $regulationId] = $this->contexto($request);
        $elo = $this->elo($request);
        $meta = $roster->brought($formatId, $elo);

        return response()->json(
            $query->forSpecies($species, $formatId, $elo, $regulationId, $meta, $this->top($request, 12, 40)) + [
                'especie' => $species->slug,
                'meta' => ['especies' => count($meta)],
            ],
        );
    }

    public function structuralPartners(
        Request $request,
        StructuralPartnerQuery $query,
        SetBuilder $builder,
        SpeedContext $speed,
        MetaRoster $roster,
        ClosingQuery $cierre,
        string $slug,
    ): JsonResponse {
        $species = $this->species($slug);
        [$formatId, $regulationId] = $this->contexto($request);
        $elo = $this->elo($request);
        $meta = $roster->brought($formatId, $elo);

        $conjunto = $builder->build($species, $regulationId, $meta);
        $ritmo = $speed->forSpecies(json_decode((string) $species->base_stats, true) ?: [], $meta);
        $sinCubrir = $this->sinCubrir($conjunto['cobertura']['resisten'], $meta);
        $excluir = array_filter(explode(',', (string) $request->query('equipo', '')));

        return response()->json([
            'especie' => $species->slug,
            'ritmo' => $ritmo['ritmo'],
            'cubos' => $query->forSpecies(
                $species,
                $regulationId,
                $meta,
                $ritmo,
                $sinCubrir,
                $cierre->rates($formatId, $elo, $this->min($request)),
                $excluir,
                $this->top($request, 4, 12),
            ),
        ]);
    }

    private function contexto(Request $request): array
    {
        $formatId = $this->formatId($request);

        return [$formatId, (int) DB::table('formats')->where('id', $formatId)->value('regulation_id')];
    }

    private function sinCubrir(array $resisten, array $meta): array
    {
        $porSlug = array_column($meta, null, 'slug');

        return array_map(
            fn (array $fila) => $fila + ['tipos' => $porSlug[$fila['slug']]['tipos'] ?? []],
            $resisten,
        );
    }

    private function ficha(object $species): array
    {
        return [
            'slug' => $species->slug,
            'name' => $species->name,
            'name_es' => $species->name_es,
            'types' => json_decode((string) $species->types, true),
            'base_stats' => json_decode((string) $species->base_stats, true),
            'abilities' => $this->habilidades($species),
            'sprite' => $species->sprite_file,
            'sprite_stone' => $species->sprite_stone_slug,
        ];
    }
}
