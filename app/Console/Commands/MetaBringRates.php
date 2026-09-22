<?php

namespace App\Console\Commands;

use App\Domain\Meta\BringRateQuery;
use App\Domain\Meta\SpeciesLookup;
use Illuminate\Console\Command;

class MetaBringRates extends Command
{
    protected $signature = 'meta:bring-rates
        {--format=gen9championsvgc2026regmc : Showdown id del formato}
        {--elo=0 : Corte minimo de ELO}
        {--min=30 : Muestra minima para publicar un porcentaje}
        {--top=25 : Cuantas filas mostrar}';

    protected $description = 'Porcentaje de veces que una especie del Team Preview acaba entrando al combate';

    public function handle(SpeciesLookup $lookup, BringRateQuery $query): int
    {
        $formatId = $lookup->formatId($this->option('format'));

        if (! $formatId) {
            $this->error('Formato desconocido: '.$this->option('format'));

            return self::FAILURE;
        }

        $min = (int) $this->option('min');
        $elo = (int) $this->option('elo');

        $universo = $query->universe($formatId, $elo);
        $rows = $query->rows($formatId, $elo, $min, (int) $this->option('top'));

        $this->newLine();
        $this->line(sprintf(
            'Equipos analizados: %d de %d (%d descartados porque la partida acabo antes de que se viera a todo el equipo)',
            $universo['completos'],
            $universo['total'],
            $universo['descartados'],
        ));
        $this->line(sprintf('Corte de ELO: %d  ·  Muestra minima: %d', $elo, $min));
        $this->newLine();

        if ($rows === []) {
            $this->warn('Ninguna especie llega a la muestra minima todavia. Hace falta recolectar mas replays.');

            return self::SUCCESS;
        }

        $this->table(
            ['Pokemon', 'N', 'Traido', 'Bring %', 'Lead %'],
            array_map(fn (array $r) => [
                $r['name'],
                $r['n'],
                $r['traido'],
                number_format($r['bring_pct'], 1).'%',
                number_format($r['lead_pct'], 1).'%',
            ], $rows),
        );

        $this->line('Fuente: replays publicos de Pokemon Showdown, no el ladder de Pokemon Champions.');

        return self::SUCCESS;
    }
}
