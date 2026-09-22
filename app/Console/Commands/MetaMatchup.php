<?php

namespace App\Console\Commands;

use App\Domain\Meta\MatchupQuery;
use App\Domain\Meta\SpeciesLookup;
use Illuminate\Console\Command;

class MetaMatchup extends Command
{
    protected $signature = 'meta:matchup
        {--vs= : Especie que el rival ensena en el Team Preview}
        {--format=gen9championsvgc2026regmc : Showdown id del formato}
        {--elo=0 : Corte minimo de ELO}
        {--min=30 : Muestra minima para publicar un porcentaje}
        {--top=25 : Cuantas filas mostrar}';

    protected $description = 'Como cambia el bring rate cuando el rival ensena una especie concreta';

    public function handle(SpeciesLookup $lookup, MatchupQuery $query): int
    {
        $formatId = $lookup->formatId($this->option('format'));

        if (! $formatId) {
            $this->error('Formato desconocido: '.$this->option('format'));

            return self::FAILURE;
        }

        $vs = trim((string) $this->option('vs'));

        if ($vs === '') {
            $this->error('Falta --vs con la especie que ensena el rival.');

            return self::FAILURE;
        }

        $rival = $lookup->find($vs);

        if (! $rival) {
            $this->error("Especie desconocida: {$vs}");

            return self::FAILURE;
        }

        $elo = (int) $this->option('elo');
        $min = (int) $this->option('min');

        $universo = $query->universe($formatId, $elo, $rival->id);
        $resultado = $query->rows($formatId, $elo, $rival->id, $min);

        $this->newLine();
        $this->line(sprintf(
            'Lados que se enfrentaron a %s: %d de %d equipos completos',
            $rival->name,
            $universo['enfrentados'],
            $universo['completos'],
        ));
        $this->line(sprintf('Corte de ELO: %d  ·  Muestra minima: %d', $elo, $min));
        $this->newLine();

        if ($resultado['filas'] === []) {
            $this->warn(sprintf(
                'Ninguna especie llega a %d observaciones en los dos grupos. Hace falta recolectar mas replays o bajar --min.',
                $min,
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Pokemon', 'N', 'Trae % (con '.$rival->name.')', 'Trae % (sin)', 'Delta'],
            array_map(fn (array $fila) => [
                $fila['name'],
                $fila['n'],
                number_format($fila['bring_pct'], 1).'%',
                number_format($fila['bring_pct_sin'], 1).'%',
                sprintf('%+.1f%s', $fila['delta'], $fila['significativo'] ? ' *' : ''),
            ], array_slice($resultado['filas'], 0, (int) $this->option('top'))),
        );

        if ($resultado['ocultas'] > 0) {
            $this->line(sprintf('%d especies ocultas por no llegar a la muestra minima.', $resultado['ocultas']));
        }

        $this->line('* el cambio no se explica por el tamano de la muestra (intervalo de Wilson al 95%).');
        $this->line('Es una descripcion, no una causa: quien lleva '.$rival->name.' suele llevar un equipo entero alrededor.');
        $this->line('Fuente: replays publicos de Pokemon Showdown, no el ladder de Pokemon Champions.');

        return self::SUCCESS;
    }
}
