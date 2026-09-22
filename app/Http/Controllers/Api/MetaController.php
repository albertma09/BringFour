<?php

namespace App\Http\Controllers\Api;

use App\Domain\Meta\BehaviorQuery;
use App\Domain\Meta\BringRateQuery;
use App\Domain\Meta\MatchupQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MetaController extends ApiController
{
    public function bringRates(Request $request, BringRateQuery $query): JsonResponse
    {
        $formatId = $this->formatId($request);
        $elo = $this->elo($request);
        $min = $this->min($request);

        return response()->json([
            'muestra' => $query->universe($formatId, $elo) + [
                'elo_bucket' => $elo,
                'min_sample' => $min,
                'fuente' => 'showdown',
            ],
            'especies' => $query->rows($formatId, $elo, $min, $this->top($request, 50)),
        ]);
    }

    public function matchups(Request $request, string $slug, MatchupQuery $query): JsonResponse
    {
        $formatId = $this->formatId($request);
        $rival = $this->species($slug);
        $elo = $this->elo($request);
        $min = $this->min($request);

        $universo = $query->universe($formatId, $elo, $rival->id);
        $resultado = $query->rows($formatId, $elo, $rival->id, $min);

        return response()->json([
            'rival' => $this->speciesPayload($rival),
            'muestra' => $universo + [
                'elo_bucket' => $elo,
                'min_sample' => $min,
                'ocultas' => $resultado['ocultas'],
                'fuente' => 'showdown',
            ],
            'especies' => array_slice($resultado['filas'], 0, $this->top($request, 50)),
        ]);
    }

    public function behavior(Request $request, string $slug, BehaviorQuery $query): JsonResponse
    {
        $formatId = $this->formatId($request);
        $actor = $this->species($slug);
        $elo = $this->elo($request);
        $top = $this->top($request, 20);

        $fase = (string) $request->query('fase', 'medio');

        if (! in_array($fase, BehaviorQuery::FASES, true)) {
            throw new BadRequestHttpException('La fase solo puede ser apertura o medio.');
        }

        $vs = trim((string) $request->query('vs', ''));

        if ($vs === '') {
            return response()->json([
                'actor' => $this->speciesPayload($actor),
                'muestra' => ['elo_bucket' => $elo, 'min_sample' => self::MIN_SAMPLE, 'fuente' => 'showdown'],
                'contextos' => $query->contexts($formatId, $actor->slug, $elo, $top),
            ]);
        }

        $rival = $this->species($vs);
        $resultado = $query->distribution($formatId, $actor->slug, $rival->slug, $fase, $elo, $top);

        return response()->json([
            'actor' => $this->speciesPayload($actor),
            'rival' => $this->speciesPayload($rival),
            'fase' => $fase,
            'muestra' => [
                'n' => $resultado['n'],
                'elo_bucket' => $elo,
                'min_sample' => self::MIN_SAMPLE,
                'fuente' => 'showdown',
            ],
            'acciones' => $resultado['filas'],
        ]);
    }

    private function speciesPayload(object $species): array
    {
        return [
            'slug' => $species->slug,
            'name' => $species->name,
            'name_es' => $species->name_es,
        ];
    }
}
