<?php

namespace App\Domain\Build;

final class FieldEffects
{
    public const CLIMAS = ['sunnyday', 'raindance', 'sandstorm', 'snowscape'];

    public const TERRENOS = ['grassyterrain', 'psychicterrain', 'electricterrain', 'mistyterrain'];

    private const PONEN = [
        'drought' => ['clima', 'sunnyday'],
        'orichalcumpulse' => ['clima', 'sunnyday'],
        'drizzle' => ['clima', 'raindance'],
        'sandstream' => ['clima', 'sandstorm'],
        'sandspit' => ['clima', 'sandstorm'],
        'snowwarning' => ['clima', 'snowscape'],
        'grassysurge' => ['terreno', 'grassyterrain'],
        'psychicsurge' => ['terreno', 'psychicterrain'],
        'electricsurge' => ['terreno', 'electricterrain'],
        'hadronengine' => ['terreno', 'electricterrain'],
        'mistysurge' => ['terreno', 'mistyterrain'],
    ];

    private const CLIMA_TIPO = [
        'sunnyday' => ['Fire' => 1.5, 'Water' => 0.5],
        'raindance' => ['Water' => 1.5, 'Fire' => 0.5],
    ];

    private const TERRENO_TIPO = [
        'grassyterrain' => ['Grass' => 1.3],
        'electricterrain' => ['Electric' => 1.3],
        'psychicterrain' => ['Psychic' => 1.3],
        'mistyterrain' => ['Dragon' => 0.5],
    ];

    private const TEMBLOR = ['earthquake', 'bulldoze', 'magnitude'];

    private const VELOCIDAD = [
        'chlorophyll' => 'sunnyday',
        'swiftswim' => 'raindance',
        'sandrush' => 'sandstorm',
        'slushrush' => 'snowscape',
    ];

    private const POTENCIA = [
        'risingvoltage' => ['terreno', 'electricterrain', 2.0],
        'expandingforce' => ['terreno', 'psychicterrain', 1.5],
        'mistyexplosion' => ['terreno', 'mistyterrain', 1.5],
        'psyblade' => ['terreno', 'electricterrain', 1.5],
    ];

    private const PRIORIDAD = [
        'grassyglide' => ['grassyterrain', 1],
    ];

    private const PRECISION = [
        'thunder' => ['raindance'],
        'hurricane' => ['raindance'],
        'blizzard' => ['snowscape'],
    ];

    private const SIN_CARGA = [
        'solarbeam' => 'sunnyday',
        'solarblade' => 'sunnyday',
    ];

    private const CAMALEON = [
        'weatherball' => ['clima', ['sunnyday' => 'Fire', 'raindance' => 'Water', 'sandstorm' => 'Rock', 'snowscape' => 'Ice']],
        'terrainpulse' => ['terreno', ['grassyterrain' => 'Grass', 'electricterrain' => 'Electric', 'psychicterrain' => 'Psychic', 'mistyterrain' => 'Fairy']],
    ];

    public function sets(array $habilidades): ?array
    {
        foreach ($habilidades as $habilidad) {
            if (isset(self::PONEN[$habilidad])) {
                return ['tipo' => self::PONEN[$habilidad][0], 'campo' => self::PONEN[$habilidad][1], 'habilidad' => $habilidad];
            }
        }

        return null;
    }

    public function context(array $habilidades): array
    {
        $puesto = $this->sets($habilidades);

        return [
            'clima' => ($puesto['tipo'] ?? null) === 'clima' ? $puesto['campo'] : null,
            'terreno' => ($puesto['tipo'] ?? null) === 'terreno' ? $puesto['campo'] : null,
        ];
    }

    public function grounded(array $tipos, array $habilidades): bool
    {
        return ! in_array('Flying', $tipos, true) && ! in_array('levitate', $habilidades, true);
    }

    public function apply(object $move, array $tipos, array $habilidades, ?string $clima, ?string $terreno): array
    {
        $enSuelo = $this->grounded($tipos, $habilidades);
        $terrenoActivo = $enSuelo ? $terreno : null;
        $slug = $move->slug;

        $salida = [
            'x' => 1.0,
            'prioridad' => 0,
            'precision' => null,
            'tipo' => null,
            'sin_carga' => false,
            'campo' => null,
        ];

        $tipoReal = $this->camaleon($slug, $clima, $terrenoActivo);

        if ($tipoReal !== null) {
            $salida['tipo'] = $tipoReal;
            $salida['x'] *= 2.0;
            $salida['campo'] = isset(self::CAMALEON[$slug]) && self::CAMALEON[$slug][0] === 'clima' ? $clima : $terrenoActivo;
        }

        $efectivo = $salida['tipo'] ?? $move->type;

        if ($clima !== null && isset(self::CLIMA_TIPO[$clima][$efectivo])) {
            $salida['x'] *= self::CLIMA_TIPO[$clima][$efectivo];
            $salida['campo'] ??= $clima;
        }

        if ($terrenoActivo !== null && isset(self::TERRENO_TIPO[$terrenoActivo][$efectivo])) {
            $salida['x'] *= self::TERRENO_TIPO[$terrenoActivo][$efectivo];
            $salida['campo'] ??= $terrenoActivo;
        }

        if ($terreno === 'grassyterrain' && in_array($slug, self::TEMBLOR, true)) {
            $salida['x'] *= 0.5;
            $salida['campo'] ??= 'grassyterrain';
        }

        if (isset(self::POTENCIA[$slug])) {
            [$clase, $campo, $factor] = self::POTENCIA[$slug];
            $activo = $clase === 'clima' ? $clima : $terrenoActivo;

            if ($activo === $campo) {
                $salida['x'] *= $factor;
                $salida['campo'] ??= $campo;
            }
        }

        if (isset(self::PRIORIDAD[$slug]) && $terrenoActivo === self::PRIORIDAD[$slug][0]) {
            $salida['prioridad'] = self::PRIORIDAD[$slug][1];
            $salida['campo'] ??= $terrenoActivo;
        }

        if (isset(self::PRECISION[$slug]) && in_array($clima, self::PRECISION[$slug], true)) {
            $salida['precision'] = 100;
            $salida['campo'] ??= $clima;
        }

        if (isset(self::SIN_CARGA[$slug]) && $clima === self::SIN_CARGA[$slug]) {
            $salida['sin_carga'] = true;
            $salida['campo'] ??= $clima;
        }

        return $salida;
    }

    public function speedMultiplier(array $habilidades, ?string $clima): array
    {
        foreach ($habilidades as $habilidad) {
            if (isset(self::VELOCIDAD[$habilidad]) && self::VELOCIDAD[$habilidad] === $clima) {
                return ['x' => 2.0, 'habilidad' => $habilidad];
            }
        }

        return ['x' => 1.0, 'habilidad' => null];
    }

    public function needsWeather(array $habilidades): ?string
    {
        foreach ($habilidades as $habilidad) {
            if (isset(self::VELOCIDAD[$habilidad])) {
                return self::VELOCIDAD[$habilidad];
            }
        }

        return null;
    }

    public function boosts(?string $campo, string $tipo): bool
    {
        return ($campo !== null)
            && (((self::CLIMA_TIPO[$campo][$tipo] ?? 1.0) > 1.0) || ((self::TERRENO_TIPO[$campo][$tipo] ?? 1.0) > 1.0));
    }

    public function blocksPriority(?string $terreno): bool
    {
        return $terreno === 'psychicterrain';
    }

    private function camaleon(string $slug, ?string $clima, ?string $terreno): ?string
    {
        if (! isset(self::CAMALEON[$slug])) {
            return null;
        }

        [$clase, $mapa] = self::CAMALEON[$slug];
        $activo = $clase === 'clima' ? $clima : $terreno;

        return $activo === null ? null : ($mapa[$activo] ?? null);
    }
}
