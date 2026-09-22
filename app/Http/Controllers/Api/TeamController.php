<?php

namespace App\Http\Controllers\Api;

use App\Domain\Meta\TeamQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TeamController extends ApiController
{
    public function cores(Request $request, TeamQuery $query): JsonResponse
    {
        $tamano = (int) $request->query('size', 2);

        if (! in_array($tamano, TeamQuery::TAMANOS, true)) {
            $tamano = 2;
        }

        $formatId = $this->formatId($request);
        $elo = $this->elo($request);
        $min = $this->min($request);

        return response()->json([
            'tamano' => $tamano,
            'muestra' => ['elo_bucket' => $elo, 'min_sample' => $min, 'fuente' => 'showdown'],
            'cores' => $query->cores($formatId, $elo, $tamano, $min, $this->top($request, 24, 60)),
        ]);
    }

    public function index(Request $request, TeamQuery $query): JsonResponse
    {
        $formatId = $this->formatId($request);
        $elo = $this->elo($request);
        $min = $this->min($request);

        return response()->json([
            'muestra' => ['elo_bucket' => $elo, 'min_sample' => $min, 'fuente' => 'showdown'],
            'equipos' => $query->teams($formatId, $elo, $min, $this->top($request, 24, 60)),
        ]);
    }

    public function show(Request $request, string $id, TeamQuery $query): JsonResponse
    {
        $slugs = array_values(array_filter(explode('-', $id)));
        $equipo = $query->team($this->formatId($request), $this->elo($request), $slugs);

        if ($equipo === null) {
            throw new NotFoundHttpException("Equipo desconocido: {$id}");
        }

        return response()->json([
            'muestra' => ['elo_bucket' => $this->elo($request), 'min_sample' => self::MIN_SAMPLE, 'fuente' => 'showdown'],
            'equipo' => $equipo,
        ]);
    }
}
