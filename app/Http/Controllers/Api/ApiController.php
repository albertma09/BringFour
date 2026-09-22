<?php

namespace App\Http\Controllers\Api;

use App\Domain\Meta\SpeciesLookup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class ApiController extends Controller
{
    public const MIN_SAMPLE = 30;

    private const ELO_BUCKETS = [0, 1500, 1630, 1760];

    public function __construct(protected SpeciesLookup $lookup) {}

    protected function formatId(Request $request): int
    {
        $showdownId = (string) $request->query('format', 'gen9championsvgc2026regmc');
        $formatId = $this->lookup->formatId($showdownId);

        if ($formatId === null) {
            throw new NotFoundHttpException("Formato desconocido: {$showdownId}");
        }

        return $formatId;
    }

    protected function species(string $slug): object
    {
        $species = $this->lookup->find($slug);

        if ($species === null) {
            throw new NotFoundHttpException("Especie desconocida: {$slug}");
        }

        return $species;
    }

    protected function elo(Request $request): int
    {
        $elo = (int) $request->query('elo', 0);

        return in_array($elo, self::ELO_BUCKETS, true) ? $elo : 0;
    }

    protected function min(Request $request): int
    {
        return max(self::MIN_SAMPLE, (int) $request->query('min', self::MIN_SAMPLE));
    }

    protected function top(Request $request, int $porDefecto, int $tope = 200): int
    {
        $top = (int) $request->query('top', $porDefecto);

        return max(1, min($tope, $top));
    }
}
