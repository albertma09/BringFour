<?php

namespace App\Domain\Build;

use Illuminate\Support\Facades\DB;

final class RoleClassifier
{
    public const CUBOS = ['fisico', 'especial', 'velocidad', 'apoyo', 'remate'];

    public const ORDEN_RELLENO = ['remate', 'velocidad', 'apoyo', 'fisico', 'especial'];

    private const OFENSIVO = 100;

    private const MIXTO = 15;

    private const CONTROL = ['tailwind', 'trickroom', 'icywind', 'electroweb', 'thunderwave'];

    private const REDIRECCION = ['followme', 'ragepowder'];

    private const PANTALLAS = ['lightscreen', 'reflect', 'auroraveil'];

    private const SUBIDA = [
        'swordsdance', 'nastyplot', 'calmmind', 'dragondance', 'bulkup',
        'bellydrum', 'quiverdance', 'shellsmash', 'victorydance', 'tidyup',
    ];

    private const CLIMA = ['drizzle', 'drought', 'sandstream', 'snowwarning', 'orichalcumpulse', 'desolateland', 'primordialsea'];

    private const TERRENO = ['grassysurge', 'psychicsurge', 'electricsurge', 'mistysurge', 'hadronengine'];

    public function forMany(array $slugs, int $regulationId): array
    {
        if ($slugs === []) {
            return [];
        }

        $repertorios = $this->repertorios($slugs, $regulationId);
        $salida = [];

        foreach ($this->especies($slugs) as $fila) {
            $salida[$fila->slug] = $this->clasificar(
                json_decode((string) $fila->base_stats, true) ?: [],
                array_values(json_decode((string) $fila->abilities, true) ?: []),
                $repertorios[$fila->slug] ?? [],
            );
        }

        return $salida;
    }

    public function forSpecies(object $species, int $regulationId): array
    {
        return $this->forMany([$species->slug], $regulationId)[$species->slug]
            ?? $this->clasificar([], [], []);
    }

    public function clasificar(array $baseStats, array $habilidades, array $movimientos): array
    {
        $eje = $this->eje($baseStats);
        $etiquetas = $this->etiquetas($baseStats, $habilidades, $movimientos);

        return [
            'eje' => $eje,
            'etiquetas' => $etiquetas,
            'cubos' => $this->cubos($eje, $etiquetas),
        ];
    }

    private function eje(array $baseStats): string
    {
        $fisico = (int) ($baseStats['atk'] ?? 0);
        $especial = (int) ($baseStats['spa'] ?? 0);

        if (max($fisico, $especial) < self::OFENSIVO) {
            return 'apoyo';
        }

        if (abs($fisico - $especial) <= self::MIXTO && min($fisico, $especial) >= self::OFENSIVO - self::MIXTO) {
            return 'mixto';
        }

        return $especial > $fisico ? 'especial' : 'fisico';
    }

    private function etiquetas(array $baseStats, array $habilidades, array $movimientos): array
    {
        $etiquetas = [];

        foreach (['velocidad' => self::CONTROL, 'redireccion' => self::REDIRECCION, 'pantallas' => self::PANTALLAS] as $clave => $lista) {
            if (array_intersect($movimientos, $lista) !== []) {
                $etiquetas[] = $clave;
            }
        }

        foreach (['clima' => self::CLIMA, 'terreno' => self::TERRENO] as $clave => $lista) {
            if (array_intersect($habilidades, $lista) !== []) {
                $etiquetas[] = $clave;
            }
        }

        $ofensiva = max((int) ($baseStats['atk'] ?? 0), (int) ($baseStats['spa'] ?? 0));

        if ($ofensiva >= self::OFENSIVO && array_intersect($movimientos, self::SUBIDA) !== []) {
            $etiquetas[] = 'remate';
        }

        return $etiquetas;
    }

    private function cubos(string $eje, array $etiquetas): array
    {
        $cubos = [];

        if (in_array($eje, ['fisico', 'mixto'], true)) {
            $cubos[] = 'fisico';
        }

        if (in_array($eje, ['especial', 'mixto'], true)) {
            $cubos[] = 'especial';
        }

        if (in_array('velocidad', $etiquetas, true)) {
            $cubos[] = 'velocidad';
        }

        if ($eje === 'apoyo' || array_intersect($etiquetas, ['redireccion', 'pantallas']) !== []) {
            $cubos[] = 'apoyo';
        }

        if (in_array('remate', $etiquetas, true)) {
            $cubos[] = 'remate';
        }

        return $cubos;
    }

    private function especies(array $slugs): array
    {
        return DB::table('species')->whereIn('slug', $slugs)->get(['slug', 'base_stats', 'abilities'])->all();
    }

    private function repertorios(array $slugs, int $regulationId): array
    {
        $interesantes = [...self::CONTROL, ...self::REDIRECCION, ...self::PANTALLAS, ...self::SUBIDA];

        $filas = DB::table('learnsets as l')
            ->join('moves as m', 'm.id', '=', 'l.move_id')
            ->join('species as s', 's.id', '=', 'l.species_id')
            ->whereIn('s.slug', $slugs)
            ->where('l.regulation_id', $regulationId)
            ->whereIn('m.slug', $interesantes)
            ->get(['s.slug as especie', 'm.slug as movimiento']);

        $salida = [];

        foreach ($filas as $fila) {
            $salida[$fila->especie][] = $fila->movimiento;
        }

        return $salida;
    }
}
