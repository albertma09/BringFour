<?php

namespace App\Domain\Build;

final class SwapAdvisor
{
    private const MINIMO_EQUIPO = 3;

    private const PESO_PAPEL = 25.0;

    private const HUECO_SERIO = 4.0;

    public function __construct(private TypeChart $chart) {}

    public function advise(array $analisis, array $meta, array $papeles, array $cierres, array $pesos, int $top): array
    {
        $miembros = $analisis['miembros'];

        if (count($miembros) < self::MINIMO_EQUIPO) {
            return ['redundantes' => [], 'propuesta' => null];
        }

        $aportes = $this->aportes($miembros, $pesos);
        $redundantes = $this->redundantes($miembros);
        $hueco = $this->hueco($analisis);

        if ($redundantes === [] || $hueco === null) {
            return ['redundantes' => $redundantes, 'propuesta' => null];
        }

        $sale = $this->sacrificable($redundantes, $aportes);

        return [
            'redundantes' => $redundantes,
            'propuesta' => [
                'sale' => $sale,
                'pareja' => $this->parejaDe($sale['slug'], $redundantes),
                'arregla' => $hueco,
                'entran' => $this->entran($hueco, $meta, $papeles, $cierres, $miembros, $analisis, $top),
            ],
        ];
    }

    private function aportes(array $miembros, array $pesos): array
    {
        $salida = [];

        foreach ($miembros as $miembro) {
            $otros = array_values(array_filter($miembros, fn (array $m) => $m['slug'] !== $miembro['slug']));

            $unicas = array_values(array_filter(
                $miembro['resistencias'],
                fn (string $tipo) => ! $this->alguienResiste($otros, $tipo),
            ));

            $papelesUnicos = array_values(array_filter(
                $miembro['etiquetas'],
                fn (string $etiqueta) => ! $this->alguienEtiqueta($otros, $etiqueta),
            ));

            $valor = array_sum(array_map(fn (string $tipo) => $pesos[$tipo] ?? 0.0, $unicas))
                + count($papelesUnicos) * self::PESO_PAPEL;

            $salida[$miembro['slug']] = [
                'resistencias' => $unicas,
                'papeles' => $papelesUnicos,
                'valor' => round($valor, 1),
            ];
        }

        return $salida;
    }

    private function redundantes(array $miembros): array
    {
        $parejas = [];

        for ($i = 0; $i < count($miembros); $i++) {
            for ($j = $i + 1; $j < count($miembros); $j++) {
                $uno = $miembros[$i];
                $dos = $miembros[$j];

                if ($uno['eje'] !== $dos['eje']) {
                    continue;
                }

                $comunes = array_values(array_intersect($uno['debilidades'], $dos['debilidades']));

                if ($comunes === []) {
                    continue;
                }

                $parejas[] = [
                    'a' => ['slug' => $uno['slug'], 'name' => $uno['name'], 'name_es' => $uno['name_es'], 'sprite' => $uno['sprite'], 'sprite_stone' => $uno['sprite_stone']],
                    'b' => ['slug' => $dos['slug'], 'name' => $dos['name'], 'name_es' => $dos['name_es'], 'sprite' => $dos['sprite'], 'sprite_stone' => $dos['sprite_stone']],
                    'eje' => $uno['eje'],
                    'debilidades' => $comunes,
                ];
            }
        }

        usort($parejas, fn (array $a, array $b) => count($b['debilidades']) <=> count($a['debilidades']));

        return $parejas;
    }

    private function hueco(array $analisis): ?array
    {
        foreach ($analisis['compartidas'] as $compartida) {
            if ($compartida['resisten'] === 0) {
                return ['clave' => 'compartida', 'tipo' => $compartida['tipo'], 'cuantos' => $compartida['cuantos'], 'peso' => $compartida['peso']];
            }
        }

        foreach ($analisis['sin_resistir'] as $falta) {
            if ($falta['peso'] >= self::HUECO_SERIO) {
                return ['clave' => 'sin_resistir', 'tipo' => $falta['tipo'], 'cuantos' => 0, 'peso' => $falta['peso']];
            }
        }

        if ($analisis['papeles']['faltan'] !== []) {
            return ['clave' => 'papel', 'papel' => $analisis['papeles']['faltan'][0], 'tipo' => null, 'peso' => 0.0];
        }

        return null;
    }

    private function sacrificable(array $redundantes, array $aportes): array
    {
        $candidatos = [];

        foreach ($redundantes as $pareja) {
            foreach (['a', 'b'] as $lado) {
                $slug = $pareja[$lado]['slug'];
                $candidatos[$slug] = $pareja[$lado] + ['aporta' => $aportes[$slug] ?? ['resistencias' => [], 'papeles' => [], 'valor' => 0.0]];
            }
        }

        usort($candidatos, fn (array $a, array $b) => $a['aporta']['valor'] <=> $b['aporta']['valor']);

        return array_values($candidatos)[0];
    }

    private function parejaDe(string $slug, array $redundantes): ?array
    {
        foreach ($redundantes as $pareja) {
            if ($pareja['a']['slug'] === $slug) {
                return $pareja['b'] + ['debilidades' => $pareja['debilidades']];
            }

            if ($pareja['b']['slug'] === $slug) {
                return $pareja['a'] + ['debilidades' => $pareja['debilidades']];
            }
        }

        return null;
    }

    private function entran(array $hueco, array $meta, array $papeles, array $cierres, array $miembros, array $analisis, int $top): array
    {
        $dentro = array_column($miembros, 'slug');
        $candidatos = [];

        foreach ($meta as $rival) {
            if (in_array($rival['slug'], $dentro, true)) {
                continue;
            }

            $papel = $papeles[$rival['slug']] ?? ['eje' => 'apoyo', 'etiquetas' => [], 'cubos' => []];
            $razones = [];

            if ($hueco['tipo'] !== null) {
                if ($this->chart->multiplier($hueco['tipo'], $rival['tipos']) >= 1.0) {
                    continue;
                }

                $razones[] = ['clave' => 'resiste', 'tipo' => $hueco['tipo']];
            }

            if ($hueco['clave'] === 'papel') {
                $cumple = $hueco['papel'] === 'fisico'
                    ? in_array($papel['eje'], ['fisico', 'mixto'], true)
                    : ($hueco['papel'] === 'especial'
                        ? in_array($papel['eje'], ['especial', 'mixto'], true)
                        : in_array($hueco['papel'], $papel['etiquetas'], true));

                if (! $cumple) {
                    continue;
                }

                $razones[] = ['clave' => 'papel', 'papel' => $hueco['papel']];
            }

            foreach ($analisis['papeles']['faltan'] as $falta) {
                if (in_array($falta, $papel['etiquetas'], true) && $falta !== ($hueco['papel'] ?? null)) {
                    $razones[] = ['clave' => 'papel', 'papel' => $falta];
                }
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
                'eje' => $papel['eje'],
                'etiquetas' => $papel['etiquetas'],
                'peso' => $rival['peso'],
                'cierre' => $cierres[$rival['slug']] ?? null,
                'razones' => $razones,
            ];
        }

        usort($candidatos, fn (array $a, array $b) => [count($b['razones']), $b['peso']] <=> [count($a['razones']), $a['peso']]);

        return array_slice($candidatos, 0, $top);
    }

    private function alguienResiste(array $miembros, string $tipo): bool
    {
        foreach ($miembros as $miembro) {
            if (in_array($tipo, $miembro['resistencias'], true)) {
                return true;
            }
        }

        return false;
    }

    private function alguienEtiqueta(array $miembros, string $etiqueta): bool
    {
        foreach ($miembros as $miembro) {
            if (in_array($etiqueta, $miembro['etiquetas'], true)) {
                return true;
            }
        }

        return false;
    }
}
