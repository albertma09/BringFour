<?php

namespace App\Domain\Build;

final class MovePower
{
    public const AREA = ['allAdjacentFoes', 'allAdjacent'];

    private const POR_FLAG = [
        'megalauncher' => ['pulse', 1.5],
        'strongjaw' => ['bite', 1.5],
        'sharpness' => ['slicing', 1.5],
        'toughclaws' => ['contact', 1.3],
        'punkrock' => ['sound', 1.3],
        'ironfist' => ['punch', 1.2],
    ];

    private const POR_TIPO = [
        'waterbubble' => ['Water', 2.0],
        'dragonsmaw' => ['Dragon', 1.5],
        'steelworker' => ['Steel', 1.5],
        'steelyspirit' => ['Steel', 1.5],
        'rockypayload' => ['Rock', 1.5],
        'transistor' => ['Electric', 1.3],
    ];

    private const DESCARTADOS = ['recharge', 'charge'];

    private const PRIORIDAD_MINIMA = -2;

    public function __construct(private TypeChart $chart, private FieldEffects $field) {}

    public function score(object $move, array $tipos, array $habilidades, ?string $clima = null, ?string $terreno = null): ?array
    {
        $base = (int) ($move->power ?? 0);

        if ($base <= 0 || (int) ($move->priority ?? 0) < self::PRIORIDAD_MINIMA) {
            return null;
        }

        $campo = $this->field->apply($move, $tipos, $habilidades, $clima, $terreno);

        if ($this->descartado($move, $campo['sin_carga'])) {
            return null;
        }

        $flags = $this->flags($move);
        $tipoReal = $campo['tipo'] ?? $move->type;
        $stab = in_array($tipoReal, $tipos, true)
            ? (in_array('adaptability', $habilidades, true) ? 2.0 : 1.5)
            : 1.0;

        [$habilidad, $porHabilidad] = $this->porHabilidad($move, $flags, $base, $habilidades);
        $area = in_array($move->target, self::AREA, true) ? 1.5 : 1.0;
        $exacta = $campo['precision'] ?? $move->accuracy;
        $precision = $exacta === null ? 1.0 : ((int) $exacta) / 100;
        $conCampo = $porHabilidad * $campo['x'];

        return [
            'base' => $base,
            'potencia' => (int) round($base * $conCampo),
            'efectiva' => round($base * $stab * $conCampo * $area * $precision, 1),
            'por_objetivo' => round($base * $stab * $conCampo * $precision, 1),
            'sin_campo' => round($base * $stab * $porHabilidad * $precision, 1),
            'prioridad' => (int) ($move->priority ?? 0) + $campo['prioridad'],
            'stab' => $stab > 1.0,
            'habilidad' => $habilidad,
            'area' => $area > 1.0,
            'golpea_aliado' => $move->target === 'allAdjacent',
            'infalible' => $exacta === null,
            'tipo_real' => $tipoReal,
            'campo' => $campo['campo'],
            'prioridad_campo' => $campo['prioridad'],
        ];
    }

    private function porHabilidad(object $move, array $flags, int $base, array $habilidades): array
    {
        foreach ($habilidades as $habilidad) {
            if (isset(self::POR_FLAG[$habilidad]) && isset($flags[self::POR_FLAG[$habilidad][0]])) {
                return [$habilidad, self::POR_FLAG[$habilidad][1]];
            }

            if (isset(self::POR_TIPO[$habilidad]) && self::POR_TIPO[$habilidad][0] === $move->type) {
                return [$habilidad, self::POR_TIPO[$habilidad][1]];
            }

            if ($habilidad === 'technician' && $base <= 60) {
                return [$habilidad, 1.5];
            }

            if ($habilidad === 'sheerforce' && $move->secondary !== null && $move->secondary !== 'null') {
                return [$habilidad, 1.3];
            }
        }

        return [null, 1.0];
    }

    public function category(array $baseStats): string
    {
        return ((int) ($baseStats['spa'] ?? 0)) > ((int) ($baseStats['atk'] ?? 0)) ? 'Special' : 'Physical';
    }

    public function coverage(array $movimientos, array $tiposDefensores): float
    {
        $mejor = 0.0;

        foreach ($movimientos as $movimiento) {
            $mejor = max($mejor, $this->chart->multiplier($movimiento['type'], $tiposDefensores));
        }

        return $mejor;
    }

    private function descartado(object $move, bool $sinCarga): bool
    {
        $flags = $this->flags($move);

        foreach (self::DESCARTADOS as $flag) {
            if (! isset($flags[$flag])) {
                continue;
            }

            if ($flag === 'charge' && $sinCarga) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function flags(object $move): array
    {
        return json_decode((string) $move->flags, true) ?: [];
    }
}
