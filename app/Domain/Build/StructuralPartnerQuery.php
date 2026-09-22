<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class StructuralPartnerQuery
{
    private const CONTROL = ['tailwind', 'trickroom', 'icywind', 'electroweb', 'thunderwave'];

    private const REDIRECCION = ['followme', 'ragepowder'];

    public function __construct(private TypeChart $chart, private SetBuilder $builder) {}

    public function forSpecies(object $species, int $regulationId, array $meta, array $ritmo, array $sinCubrir, int $top): array
    {
        $tipos = json_decode((string) $species->types, true) ?: [];
        $debilidades = array_keys($this->chart->weaknesses($tipos));
        $necesita = $ritmo['ritmo'] === 'rapido' ? [] : self::CONTROL;
        $candidatos = [];

        foreach ($meta as $rival) {
            if ($rival['slug'] === $species->slug) {
                continue;
            }

            $razones = [];
            $tapa = $this->tapa($rival['tipos'], $debilidades);

            if ($tapa !== []) {
                $razones[] = ['clave' => 'tapa', 'tipos' => $tapa];
            }

            $aporta = $this->aporta($rival['slug'], $regulationId, $necesita);

            if ($aporta !== []) {
                $razones[] = ['clave' => 'ritmo', 'movimientos' => $aporta];
            }

            $redirige = $this->aporta($rival['slug'], $regulationId, self::REDIRECCION);

            if ($redirige !== []) {
                $razones[] = ['clave' => 'redirige', 'movimientos' => $redirige];
            }

            $rellena = $this->rellena($rival['slug'], $regulationId, $sinCubrir);

            if ($rellena !== []) {
                $razones[] = ['clave' => 'cubre', 'tipos' => $rellena];
            }

            if ($razones === []) {
                continue;
            }

            $candidatos[] = [
                'slug' => $rival['slug'],
                'name' => $rival['name'],
                'name_es' => $rival['name_es'],
                'sprite' => $rival['sprite'],
                'sprite_stone' => $rival['sprite_stone'],
                'tipos' => $rival['tipos'],
                'peso' => $rival['peso'],
                'razones' => $razones,
                'encaje' => count($razones),
            ];
        }

        usort($candidatos, fn (array $a, array $b) => [$b['encaje'], $b['peso']] <=> [$a['encaje'], $a['peso']]);

        return array_slice($candidatos, 0, $top);
    }

    private function rellena(string $slug, int $regulationId, array $sinCubrir): array
    {
        if ($sinCubrir === []) {
            return [];
        }

        $tipos = DB::table('learnsets as l')
            ->join('moves as m', 'm.id', '=', 'l.move_id')
            ->join('species as s', 's.id', '=', 'l.species_id')
            ->where('s.slug', $slug)
            ->where('l.regulation_id', $regulationId)
            ->where('m.power', '>', 0)
            ->distinct()
            ->pluck('m.type')
            ->all();

        $resueltos = [];

        foreach ($sinCubrir as $duro) {
            foreach ($tipos as $tipo) {
                if ($this->chart->multiplier($tipo, $duro['tipos'] ?? []) >= 2.0) {
                    $resueltos[] = $duro['name'];

                    break;
                }
            }
        }

        return array_slice($resueltos, 0, 4);
    }

    private function tapa(array $tiposRival, array $debilidades): array
    {
        $tapados = [];

        foreach ($debilidades as $debilidad) {
            if ($this->chart->multiplier($debilidad, $tiposRival) < 1.0) {
                $tapados[] = $debilidad;
            }
        }

        return $tapados;
    }

    private function aporta(string $slug, int $regulationId, array $movimientos): array
    {
        if ($movimientos === []) {
            return [];
        }

        return DB::table('learnsets as l')
            ->join('moves as m', 'm.id', '=', 'l.move_id')
            ->join('species as s', 's.id', '=', 'l.species_id')
            ->where('s.slug', $slug)
            ->where('l.regulation_id', $regulationId)
            ->whereIn('m.slug', $movimientos)
            ->pluck('m.name')
            ->all();
    }
}
