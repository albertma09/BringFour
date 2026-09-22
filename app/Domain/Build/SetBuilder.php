<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class SetBuilder
{
    private const UTILIDAD = [
        'proteccion' => ['protect', 'detect', 'spikyshield', 'burningbulwark', 'silktrap', 'kingsshield', 'banefulbunker', 'obstruct'],
        'velocidad' => ['tailwind', 'trickroom', 'icywind', 'electroweb', 'thunderwave'],
        'redireccion' => ['followme', 'ragepowder'],
        'estorbo' => ['fakeout', 'taunt', 'willowisp', 'encore', 'disable', 'spore', 'sleeppowder', 'yawn'],
        'apoyo' => ['helpinghand', 'lightscreen', 'reflect', 'auroraveil', 'safeguard', 'healpulse', 'lifedew', 'wideguard'],
        'subida' => ['swordsdance', 'nastyplot', 'calmmind', 'dragondance', 'bulkup', 'irondefense', 'agility'],
    ];

    private const VIABLE = 0.6;

    public function __construct(private MovePower $power, private TypeChart $chart) {}

    public function build(object $species, int $regulationId, array $meta): array
    {
        $tipos = json_decode((string) $species->types, true) ?: [];
        $baseStats = json_decode((string) $species->base_stats, true) ?: [];
        $habilidades = array_values(json_decode((string) $species->abilities, true) ?: []);
        $categoria = $this->power->category($baseStats);

        $repertorio = $this->repertorio((int) $species->id, $regulationId);
        $ataques = $this->ataques($repertorio, $tipos, $habilidades, $categoria);
        $utilidad = $this->utilidad($repertorio);

        $elegidos = $this->elegir($ataques, $utilidad, $meta);

        return [
            'categoria' => $categoria,
            'habilidad' => $habilidades[0] ?? null,
            'movimientos' => $elegidos,
            'alternativas' => array_slice(array_values(array_filter(
                $ataques,
                fn (array $m) => ! in_array($m['slug'], array_column($elegidos, 'slug'), true),
            )), 0, 6),
            'utilidad' => $utilidad,
            'cobertura' => $this->cobertura($elegidos, $meta),
        ];
    }

    private function repertorio(int $speciesId, int $regulationId): array
    {
        return DB::select('
            select m.slug, m.name, m.name_es, m.type, m.category, m.power, m.accuracy,
                   m.target, m.flags, m.secondary, m.priority
            from learnsets l
            join moves m on m.id = l.move_id
            where l.species_id = ? and l.regulation_id = ?
        ', [$speciesId, $regulationId]);
    }

    private function ataques(array $repertorio, array $tipos, array $habilidades, string $categoria): array
    {
        $salida = [];

        foreach ($repertorio as $move) {
            if ($move->category !== $categoria || $this->esUtilidad($move->slug) !== null) {
                continue;
            }

            $nota = $this->power->score($move, $tipos, $habilidades);

            if ($nota === null) {
                continue;
            }

            $salida[] = $this->fila($move) + $nota;
        }

        usort($salida, fn (array $a, array $b) => $b['efectiva'] <=> $a['efectiva']);

        return $salida;
    }

    private function utilidad(array $repertorio): array
    {
        $salida = [];

        foreach ($repertorio as $move) {
            $funcion = $this->esUtilidad($move->slug);

            if ($funcion !== null) {
                $salida[] = $this->fila($move) + ['funcion' => $funcion];
            }
        }

        usort($salida, fn (array $a, array $b) => array_search($a['funcion'], array_keys(self::UTILIDAD), true)
            <=> array_search($b['funcion'], array_keys(self::UTILIDAD), true));

        return $salida;
    }

    private function elegir(array $ataques, array $utilidad, array $meta): array
    {
        $elegidos = [];
        $proteccion = array_values(array_filter($utilidad, fn (array $m) => $m['funcion'] === 'proteccion'));

        if ($proteccion !== []) {
            $elegidos[] = $proteccion[0] + ['motivo' => 'proteccion'];
        }

        $limpios = array_values(array_filter($ataques, fn (array $m) => ! ($m['golpea_aliado'] ?? false)));
        $stab = array_values(array_filter($limpios, fn (array $m) => $m['stab']));

        if ($stab !== []) {
            $elegidos[] = $stab[0] + ['motivo' => 'stab'];
        }

        $viables = $this->viables($limpios);

        while (count($elegidos) < 4) {
            $mejor = $this->mejorCobertura($viables, $elegidos, $meta);

            if ($mejor === null) {
                break;
            }

            $elegidos[] = $mejor + ['motivo' => 'cobertura'];
        }

        if (count($elegidos) < 4 && $utilidad !== []) {
            foreach ($utilidad as $movimiento) {
                if (count($elegidos) >= 4) {
                    break;
                }

                if (! in_array($movimiento['slug'], array_column($elegidos, 'slug'), true)) {
                    $elegidos[] = $movimiento + ['motivo' => $movimiento['funcion']];
                }
            }
        }

        return $elegidos;
    }

    private function viables(array $ataques): array
    {
        if ($ataques === []) {
            return [];
        }

        $techo = max(array_column($ataques, 'por_objetivo'));

        return array_values(array_filter($ataques, fn (array $m) => $m['por_objetivo'] >= $techo * self::VIABLE));
    }

    private function mejorCobertura(array $ataques, array $elegidos, array $meta): ?array
    {
        $yaEstan = array_column($elegidos, 'slug');
        $tiposPuestos = array_column($elegidos, 'type');
        $actual = $this->cobertura($elegidos, $meta)['pct'];
        $mejor = null;
        $mejorGanancia = -1.0;

        foreach ($ataques as $ataque) {
            if (in_array($ataque['slug'], $yaEstan, true) || in_array($ataque['type'], $tiposPuestos, true)) {
                continue;
            }

            $ganancia = $this->cobertura([...$elegidos, $ataque], $meta)['pct'] - $actual;

            if ($ganancia > $mejorGanancia || ($ganancia === $mejorGanancia && $mejor !== null && $ataque['efectiva'] > $mejor['efectiva'])) {
                $mejorGanancia = $ganancia;
                $mejor = $ataque;
            }
        }

        return $mejor;
    }

    private function cobertura(array $movimientos, array $meta): array
    {
        $ataques = array_values(array_filter($movimientos, fn (array $m) => ($m['power'] ?? 0) > 0));

        if ($meta === [] || $ataques === []) {
            return ['pct' => 0.0, 'resisten' => []];
        }

        $total = 0;
        $golpeados = 0;
        $resisten = [];

        foreach ($meta as $rival) {
            $total += $rival['peso'];
            $multiplicador = $this->power->coverage($ataques, $rival['tipos']);

            if ($multiplicador >= 2.0) {
                $golpeados += $rival['peso'];

                continue;
            }

            if ($multiplicador < 1.0) {
                $resisten[] = ['slug' => $rival['slug'], 'name' => $rival['name'], 'name_es' => $rival['name_es'], 'x' => $multiplicador];
            }
        }

        return [
            'pct' => $total > 0 ? round(100 * $golpeados / $total, 1) : 0.0,
            'resisten' => array_slice($resisten, 0, 8),
        ];
    }

    private function esUtilidad(string $slug): ?string
    {
        foreach (self::UTILIDAD as $funcion => $slugs) {
            if (in_array($slug, $slugs, true)) {
                return $funcion;
            }
        }

        return null;
    }

    private function fila(object $move): array
    {
        return [
            'slug' => $move->slug,
            'name' => $move->name,
            'name_es' => $move->name_es,
            'type' => $move->type,
            'category' => $move->category,
            'power' => $move->power,
            'accuracy' => $move->accuracy,
            'target' => $move->target,
        ];
    }
}
