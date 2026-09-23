<?php

namespace App\Domain\Build;

use App\Domain\Meta\SetQuery;
use App\Domain\Stats\SpreadAdvisor;
use Illuminate\Support\Facades\DB;

final class SlotDefaults
{
    private const HUECOS = 4;

    private const SIN_MINIMO = 1;

    private const CUOTA = 0.15;

    private const ATACANTES = 2;

    private const RAPIDO = ['atk' => 'jolly', 'spa' => 'timid'];

    private const OFENSIVO = ['atk' => 'adamant', 'spa' => 'modest'];

    private const APOYO = [
        'def' => ['atk' => 'impish', 'spa' => 'bold'],
        'spd' => ['atk' => 'careful', 'spa' => 'calm'],
    ];

    public function __construct(
        private SetQuery $sets,
        private SetBuilder $builder,
        private SpreadAdvisor $advisor,
        private SpeedContext $speed,
    ) {}

    public function forSpecies(object $species, int $formatId, int $elo, int $regulationId, array $meta): array
    {
        $visto = $this->sets->forSpecies((int) $species->id, $formatId, $elo, self::SIN_MINIMO, self::HUECOS);
        $deducido = $this->builder->build($species, $regulationId, $meta);

        $movimientos = $this->movimientos($visto, $deducido);
        $habilidad = $this->habilidad($species, $visto, $deducido);
        $objeto = $this->objeto($visto);
        $reparto = $this->reparto($species, $meta, $movimientos);

        return [
            'hueco' => [
                'slug' => $species->slug,
                'movimientos' => array_column($movimientos, 'slug'),
                'habilidad' => $habilidad['slug'] ?? null,
                'objeto' => $objeto['slug'] ?? null,
                'sp' => $reparto['sp'],
                'alineamiento' => $reparto['alineamiento'],
            ],
            'fuente' => [
                'traidas' => $visto['traidas'],
                'vistos' => count(array_filter($movimientos, fn (array $m) => $m['origen'] === 'visto')),
                'deducidos' => count(array_filter($movimientos, fn (array $m) => $m['origen'] === 'deducido')),
                'movimientos' => array_map(fn (array $m) => ['slug' => $m['slug'], 'origen' => $m['origen'], 'n' => $m['n']], $movimientos),
                'habilidad' => ['n' => $habilidad['n'] ?? null, 'cuota' => $habilidad['cuota'] ?? null],
                'objeto' => ['n' => $objeto['n'] ?? null, 'cuota' => $objeto['cuota'] ?? null],
                'sp' => 'deducido',
            ],
        ];
    }

    private function movimientos(array $visto, array $deducido): array
    {
        $salida = [];

        foreach ($visto['movimientos'] as $movimiento) {
            $salida[] = [
                'slug' => $movimiento['slug'],
                'category' => $movimiento['category'],
                'origen' => 'visto',
                'n' => $movimiento['n'],
            ];
        }

        foreach ($deducido['movimientos'] as $movimiento) {
            if (count($salida) >= self::HUECOS) {
                break;
            }

            if (! in_array($movimiento['slug'], array_column($salida, 'slug'), true)) {
                $salida[] = [
                    'slug' => $movimiento['slug'],
                    'category' => $movimiento['category'],
                    'origen' => 'deducido',
                    'n' => null,
                ];
            }
        }

        return array_slice($salida, 0, self::HUECOS);
    }

    private function habilidad(object $species, array $visto, array $deducido): ?array
    {
        $suyas = array_values(json_decode((string) $species->abilities, true) ?: []);

        foreach ($visto['habilidades'] as $habilidad) {
            if (in_array($habilidad['slug'], $suyas, true) && $this->respaldada($habilidad, $visto['traidas'])) {
                return ['slug' => $habilidad['slug'], 'n' => $habilidad['n'], 'cuota' => $this->cuota($habilidad, $visto['traidas'])];
            }
        }

        $primera = $deducido['habilidad'] ?? ($suyas[0] ?? null);

        return $primera === null ? null : ['slug' => $primera, 'n' => null, 'cuota' => null];
    }

    private function objeto(array $visto): ?array
    {
        $objeto = $visto['objetos'][0] ?? null;

        if ($objeto === null || ! $this->respaldada($objeto, $visto['traidas'])) {
            return null;
        }

        return ['slug' => $objeto['slug'], 'n' => $objeto['n'], 'cuota' => $this->cuota($objeto, $visto['traidas'])];
    }

    private function respaldada(array $fila, int $traidas): bool
    {
        return $traidas > 0 && $fila['n'] >= $traidas * self::CUOTA;
    }

    private function cuota(array $fila, int $traidas): float
    {
        return $traidas > 0 ? round(100 * $fila['n'] / $traidas, 1) : 0.0;
    }

    private function reparto(object $species, array $meta, array $movimientos): array
    {
        $baseStats = json_decode((string) $species->base_stats, true) ?: [];
        $habilidades = array_values(json_decode((string) $species->abilities, true) ?: []);
        $ritmo = $meta === [] ? null : $this->speed->forSpecies($baseStats, $meta, $habilidades)['ritmo'];

        $papel = $this->papel($movimientos);
        $previo = $this->advisor->suggest($baseStats, null, null, $ritmo, $papel);
        $alineamiento = $this->alineamiento($previo, $this->ofensivaReal($movimientos, $previo));
        $fila = $alineamiento === null ? null : DB::table('alignments')->where('slug', $alineamiento)->first();

        $final = $this->advisor->suggest($baseStats, $fila->plus_stat ?? null, $fila->minus_stat ?? null, $ritmo, $papel);

        return ['sp' => $final['sp'], 'alineamiento' => $fila->slug ?? null];
    }

    private function alineamiento(array $reparto, string $ofensiva): ?string
    {
        if ($reparto['ritmo'] === 'rapido') {
            return self::RAPIDO[$ofensiva] ?? null;
        }

        if ($reparto['papel'] === 'apoyo') {
            return self::APOYO[$reparto['defensa']][$ofensiva] ?? null;
        }

        return self::OFENSIVO[$ofensiva] ?? null;
    }

    private function papel(array $movimientos): string
    {
        $atacantes = count(array_filter(
            $movimientos,
            fn (array $m) => in_array($m['category'] ?? null, ['Physical', 'Special'], true),
        ));

        return $atacantes >= self::ATACANTES ? 'ofensivo' : 'apoyo';
    }

    private function ofensivaReal(array $movimientos, array $reparto): string
    {
        $categorias = array_count_values(array_filter(array_column($movimientos, 'category')));
        $fisicos = $categorias['Physical'] ?? 0;
        $especiales = $categorias['Special'] ?? 0;

        if ($fisicos === $especiales) {
            return $reparto['ofensiva'];
        }

        return $especiales > $fisicos ? 'spa' : 'atk';
    }
}
