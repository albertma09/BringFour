<?php

namespace App\Http\Controllers\Api;

use App\Domain\Build\FieldEffects;
use App\Domain\Build\MetaRoster;
use App\Domain\Build\RoleClassifier;
use App\Domain\Build\SetBuilder;
use App\Domain\Build\SpeedContext;
use App\Domain\Build\StructuralPartnerQuery;
use App\Domain\Build\SwapAdvisor;
use App\Domain\Build\TeamAnalyzer;
use App\Domain\Build\TeamOffense;
use App\Domain\Build\TypeUsage;
use App\Domain\Build\ThreatQuery;
use App\Domain\Meta\ClosingQuery;
use App\Domain\Meta\FieldQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BuildController extends ApiController
{
    public function analysis(
        Request $request,
        SetBuilder $builder,
        SpeedContext $speed,
        MetaRoster $roster,
        RoleClassifier $roles,
        ClosingQuery $cierre,
        TeamOffense $offense,
        string $slug,
    ): JsonResponse {
        $species = $this->species($slug);
        [$formatId, $regulationId] = $this->contexto($request);
        $elo = $this->elo($request);
        $meta = $roster->brought($formatId, $elo);
        $huecos = $this->huecosDelEquipo($request, $offense, $species->slug, $formatId, $elo, $regulationId, $meta);

        $conjunto = $builder->build($species, $regulationId, $meta, $huecos);
        $habilidades = array_values(json_decode((string) $species->abilities, true) ?: []);
        $contexto = app(FieldEffects::class)->context($habilidades);
        $ritmo = $speed->forSpecies(
            json_decode((string) $species->base_stats, true) ?: [],
            $meta,
            $habilidades,
            $contexto['clima'],
        );

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
        TeamOffense $offense,
        string $slug,
    ): JsonResponse {
        $species = $this->species($slug);
        [$formatId, $regulationId] = $this->contexto($request);
        $elo = $this->elo($request);
        $meta = $roster->brought($formatId, $elo);
        $huecos = $this->huecosDelEquipo($request, $offense, $species->slug, $formatId, $elo, $regulationId, $meta);

        $conjunto = $builder->build($species, $regulationId, $meta, $huecos);
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

    public function team(
        Request $request,
        TeamAnalyzer $analyzer,
        SwapAdvisor $swap,
        MetaRoster $roster,
        TypeUsage $usage,
        RoleClassifier $roles,
        ClosingQuery $cierre,
        FieldQuery $campos,
    ): JsonResponse {
        [$especies, $formatId, $elo, $regulationId] = $this->equipo($request);
        $meta = $roster->brought($formatId, $elo);
        $pesos = $usage->shares($formatId, $elo);
        $cierres = $cierre->rates($formatId, $elo, $this->min($request));

        $analisis = $analyzer->analyse($especies, $formatId, $elo, $regulationId, $meta, $pesos, $cierres, $this->min($request));
        $papeles = $roles->forMany(array_column($meta, 'slug'), $regulationId);

        return response()->json($analisis + [
            'cambio' => $swap->advise($analisis, $meta, $papeles, $cierres, $pesos, $this->top($request, 4, 12)),
            'pesos' => $pesos,
            'campos_vistos' => $campos->shares($formatId, $elo),
        ]);
    }

    public function teamPartners(
        Request $request,
        TeamAnalyzer $analyzer,
        StructuralPartnerQuery $query,
        MetaRoster $roster,
        TypeUsage $usage,
        ClosingQuery $cierre,
        TeamOffense $offense,
    ): JsonResponse {
        [$especies, $formatId, $elo, $regulationId] = $this->equipo($request);
        $meta = $roster->brought($formatId, $elo);
        $pesos = $usage->shares($formatId, $elo);
        $min = $this->min($request);
        $cierres = $cierre->rates($formatId, $elo, $min);

        $analisis = $analyzer->analyse($especies, $formatId, $elo, $regulationId, $meta, $pesos, $cierres, $min);
        $golpes = $offense->tiposVistos(array_column($meta, 'slug'), $formatId, $elo);

        return response()->json([
            'equipo' => array_column($analisis['miembros'], 'slug'),
            'cubos' => $query->forTeam($analisis, $regulationId, $meta, $cierres, $this->top($request, 4, 12), $golpes),
        ]);
    }

    private function equipo(Request $request): array
    {
        $slugs = array_slice(array_values(array_unique(array_filter(
            explode(',', (string) $request->query('equipo', '')),
        ))), 0, 6);

        if ($slugs === []) {
            throw new BadRequestHttpException('Hace falta al menos un Pokemon en el parametro equipo.');
        }

        [$formatId, $regulationId] = $this->contexto($request);

        return [array_map(fn (string $slug) => $this->species($slug), $slugs), $formatId, $this->elo($request), $regulationId];
    }

    private function huecosDelEquipo(
        Request $request,
        TeamOffense $offense,
        string $propio,
        int $formatId,
        int $elo,
        int $regulationId,
        array $meta,
    ): array {
        $slugs = array_values(array_diff(array_unique(array_filter(
            explode(',', (string) $request->query('equipo', '')),
        )), [$propio]));

        if ($slugs === []) {
            return [];
        }

        $companeros = array_map(fn (string $slug) => $this->species($slug), array_slice($slugs, 0, 5));

        return $offense->huecos($companeros, $formatId, $elo, $regulationId, $meta, $this->min($request));
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
