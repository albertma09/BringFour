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

    private const PRIORITARIO = 0.4;

    private const EMPATE = 10;

    private const HUECO = 5.0;

    public function __construct(private MovePower $power, private TypeChart $chart, private FieldEffects $field) {}

    public function build(object $species, int $regulationId, array $meta, array $huecos = []): array
    {
        $tipos = json_decode((string) $species->types, true) ?: [];
        $baseStats = json_decode((string) $species->base_stats, true) ?: [];
        $habilidades = array_values(json_decode((string) $species->abilities, true) ?: []);
        $contexto = $this->field->context($habilidades);
        $repertorio = $this->repertorio((int) $species->id, $regulationId);
        $categoria = $this->categoria($baseStats, $repertorio, $tipos, $habilidades, $contexto);
        $ataques = $this->ataques($repertorio, $tipos, $habilidades, $categoria, $contexto);
        $utilidad = $this->utilidad($repertorio);

        $elegidos = $this->elegir($ataques, $utilidad, $meta, $huecos);

        return [
            'categoria' => $categoria,
            'campo' => $this->field->sets($habilidades),
            'habilidad' => $habilidades[0] ?? null,
            'movimientos' => $elegidos,
            'alternativas' => array_slice(array_values(array_filter(
                $ataques,
                fn (array $m) => ! in_array($m['slug'], array_column($elegidos, 'slug'), true),
            )), 0, 6),
            'utilidad' => $utilidad,
            'cobertura' => $this->cobertura($elegidos, $meta),
            'ajustado_al_equipo' => $huecos !== [],
        ];
    }

    private function categoria(array $baseStats, array $repertorio, array $tipos, array $habilidades, array $contexto): string
    {
        $fisico = (int) ($baseStats['atk'] ?? 0);
        $especial = (int) ($baseStats['spa'] ?? 0);

        if (abs($fisico - $especial) > self::EMPATE) {
            return $especial > $fisico ? 'Special' : 'Physical';
        }

        $mejor = ['Physical' => 0.0, 'Special' => 0.0];

        foreach ($repertorio as $move) {
            if (! isset($mejor[$move->category])) {
                continue;
            }

            $nota = $this->power->score($move, $tipos, $habilidades, $contexto['clima'], $contexto['terreno']);
            $mejor[$move->category] = max($mejor[$move->category], $nota['por_objetivo'] ?? 0.0);
        }

        return $mejor['Special'] > $mejor['Physical'] ? 'Special' : 'Physical';
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

    private function ataques(array $repertorio, array $tipos, array $habilidades, string $categoria, array $contexto): array
    {
        $salida = [];

        foreach ($repertorio as $move) {
            if ($move->category !== $categoria || $this->esUtilidad($move->slug) !== null) {
                continue;
            }

            $nota = $this->power->score($move, $tipos, $habilidades, $contexto['clima'], $contexto['terreno']);

            if ($nota === null) {
                continue;
            }

            $salida[] = ['type' => $nota['tipo_real']] + $this->fila($move) + $nota;
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

    private function elegir(array $ataques, array $utilidad, array $meta, array $huecos): array
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

        $rapido = $this->prioritario($limpios, $elegidos);

        if ($rapido !== null) {
            $elegidos[] = $rapido + ['motivo' => 'prioridad'];
        }

        $viables = $this->viables($limpios);

        while (count($elegidos) < 4) {
            $mejor = $this->mejorCobertura($viables, $elegidos, $meta, $huecos);

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

    private function prioritario(array $ataques, array $elegidos): ?array
    {
        $yaEstan = array_column($elegidos, 'slug');
        $techo = $ataques === [] ? 0.0 : max(array_column($ataques, 'por_objetivo'));

        foreach ($ataques as $ataque) {
            if (in_array($ataque['slug'], $yaEstan, true) || ($ataque['prioridad'] ?? 0) <= 0) {
                continue;
            }

            if ($ataque['por_objetivo'] >= $techo * self::PRIORITARIO) {
                return $ataque;
            }
        }

        return null;
    }

    private function repetido(array $ataque, array $elegidos): bool
    {
        foreach ($elegidos as $puesto) {
            if (($puesto['type'] ?? null) !== $ataque['type']) {
                continue;
            }

            if (in_array($puesto['target'] ?? '', MovePower::AREA, true) === in_array($ataque['target'], MovePower::AREA, true)) {
                return true;
            }
        }

        return false;
    }

    private function viables(array $ataques): array
    {
        if ($ataques === []) {
            return [];
        }

        $techo = max(array_column($ataques, 'sin_campo'));

        return array_values(array_filter($ataques, fn (array $m) => $m['sin_campo'] >= $techo * self::VIABLE));
    }

    private function mejorCobertura(array $ataques, array $elegidos, array $meta, array $huecos): ?array
    {
        $yaEstan = array_column($elegidos, 'slug');
        $actual = $this->cobertura($elegidos, $meta)['pct'];
        $mejor = null;
        $mejorGanancia = -1.0;

        foreach ($ataques as $ataque) {
            if (in_array($ataque['slug'], $yaEstan, true) || $this->repetido($ataque, $elegidos)) {
                continue;
            }

            $ganancia = $this->cobertura([...$elegidos, $ataque], $meta)['pct'] - $actual
                + self::HUECO * $this->tapaHuecos($ataque, $huecos);

            if ($ganancia > $mejorGanancia || ($ganancia === $mejorGanancia && $mejor !== null && $ataque['efectiva'] > $mejor['efectiva'])) {
                $mejorGanancia = $ganancia;
                $mejor = $ataque;
            }
        }

        return $mejor;
    }

    private function tapaHuecos(array $ataque, array $huecos): float
    {
        if ($huecos === []) {
            return 0.0;
        }

        $tapados = count(array_filter(
            $huecos,
            fn (array $hueco) => $this->chart->multiplier($ataque['type'], $hueco['tipos'] ?? []) >= 2.0,
        ));

        return $tapados / count($huecos);
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
