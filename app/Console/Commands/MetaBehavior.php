<?php

namespace App\Console\Commands;

use App\Domain\Meta\BehaviorQuery;
use App\Domain\Meta\SpeciesLookup;
use Illuminate\Console\Command;

class MetaBehavior extends Command
{
    protected $signature = 'meta:behavior
        {--actor= : Especie que toma la decision}
        {--vs= : Especie rival que hay en el campo}
        {--fase=medio : apertura (turno 1) o medio (turno 2 en adelante)}
        {--format=gen9championsvgc2026regmc : Showdown id del formato}
        {--elo=0 : Corte de ELO}
        {--top=15 : Cuantas filas mostrar}';

    protected $description = 'Que hace la gente con una especie segun lo que tiene delante';

    public function handle(SpeciesLookup $lookup, BehaviorQuery $query): int
    {
        $formatId = $lookup->formatId($this->option('format'));

        if (! $formatId) {
            $this->error('Formato desconocido: '.$this->option('format'));

            return self::FAILURE;
        }

        $actor = $lookup->find((string) $this->option('actor'));

        if (! $actor) {
            $this->error('Falta --actor con una especie valida.');

            return self::FAILURE;
        }

        $fase = $this->option('fase');

        if (! in_array($fase, BehaviorQuery::FASES, true)) {
            $this->error('La fase solo puede ser apertura o medio.');

            return self::FAILURE;
        }

        $elo = (int) $this->option('elo');
        $top = (int) $this->option('top');
        $vs = trim((string) $this->option('vs'));

        if ($vs === '') {
            return $this->listContexts($query, $formatId, $actor, $elo, $top);
        }

        $rival = $lookup->find($vs);

        if (! $rival) {
            $this->error("Especie desconocida: {$vs}");

            return self::FAILURE;
        }

        return $this->showContext($query, $formatId, $actor, $rival, $fase, $elo, $top);
    }

    private function listContexts(BehaviorQuery $query, int $formatId, object $actor, int $elo, int $top): int
    {
        $rows = $query->contexts($formatId, $actor->slug, $elo, $top);

        $this->newLine();

        if ($rows === []) {
            $this->warn("No hay contextos con muestra suficiente para {$actor->name}.");

            return self::SUCCESS;
        }

        $this->line("Rivales con muestra suficiente frente a {$actor->name}:");
        $this->newLine();
        $this->table(
            ['Rival', 'Fase', 'Decisiones'],
            array_map(fn (array $r) => [$r['rival_name'], $r['fase'], $r['n']], $rows),
        );
        $this->line('Elige uno con --vs para ver que hace la gente.');

        return self::SUCCESS;
    }

    private function showContext(BehaviorQuery $query, int $formatId, object $actor, object $rival, string $fase, int $elo, int $top): int
    {
        $resultado = $query->distribution($formatId, $actor->slug, $rival->slug, $fase, $elo, $top);

        $this->newLine();

        if ($resultado['filas'] === []) {
            $this->warn(sprintf(
                'No hay muestra suficiente de %s frente a %s en fase %s.',
                $actor->name,
                $rival->name,
                $fase,
            ));
            $this->line('Prueba sin --vs para ver contra que rivales si hay datos.');

            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%s con %s en el campo · fase %s · %d decisiones observadas',
            $actor->name,
            $rival->name,
            $fase,
            $resultado['n'],
        ));
        $this->newLine();

        $this->table(
            ['Que hace', 'N', '%', 'Intervalo 95%'],
            array_map(fn (array $fila) => [
                $fila['tipo'] === 'switch' ? 'Cambiar' : ($fila['move_name'] ?? $fila['action_key']),
                $fila['n'],
                number_format($fila['pct'], 1).'%',
                sprintf('%.1f - %.1f%%', $fila['intervalo'][0], $fila['intervalo'][1]),
            ], $resultado['filas']),
        );

        $this->line('Solo cuenta lo que el jugador eligio: no entran los relevos obligados ni los turnos en que no pudo actuar.');
        $this->line('Fuente: replays publicos de Pokemon Showdown, no el ladder de Pokemon Champions.');

        return self::SUCCESS;
    }
}
