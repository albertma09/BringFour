<?php

namespace App\Console\Commands;

use App\Domain\Replays\ShowdownLogParser;
use App\Domain\Stats\Proportion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

    public function handle(): int
    {
        $formatId = DB::table('formats')->where('showdown_id', $this->option('format'))->value('id');

        if (! $formatId) {
            $this->error('Formato desconocido: '.$this->option('format'));

            return self::FAILURE;
        }

        $actor = $this->findSpecies((string) $this->option('actor'));

        if (! $actor) {
            $this->error('Falta --actor con una especie valida.');

            return self::FAILURE;
        }

        $elo = (int) $this->option('elo');
        $fase = $this->option('fase');

        if (! in_array($fase, ['apertura', 'medio'], true)) {
            $this->error('La fase solo puede ser apertura o medio.');

            return self::FAILURE;
        }

        $vs = trim((string) $this->option('vs'));

        if ($vs === '') {
            return $this->listContexts($formatId, $actor, $elo);
        }

        $rival = $this->findSpecies($vs);

        if (! $rival) {
            $this->error("Especie desconocida: {$vs}");

            return self::FAILURE;
        }

        return $this->showContext($formatId, $actor, $rival, $fase, $elo);
    }

    private function listContexts(int $formatId, object $actor, int $elo): int
    {
        $rows = DB::select('
            select context->>\'rival\' as rival, context->>\'fase\' as fase, sum(n) as total
            from behavior_priors
            where format_id = ? and prior_type = ? and elo_bucket = ? and context->>\'actor\' = ?
            group by 1, 2
            order by sum(n) desc
            limit ?
        ', [$formatId, BehaviorBuild::PRIOR_TYPE, $elo, $actor->slug, (int) $this->option('top')]);

        $this->newLine();

        if ($rows === []) {
            $this->warn("No hay contextos con muestra suficiente para {$actor->name}.");

            return self::SUCCESS;
        }

        $this->line("Rivales con muestra suficiente frente a {$actor->name}:");
        $this->newLine();
        $this->table(
            ['Rival', 'Fase', 'Decisiones'],
            array_map(fn ($r) => [$this->nameOf($r->rival), $r->fase, $r->total], $rows),
        );
        $this->line('Elige uno con --vs para ver que hace la gente.');

        return self::SUCCESS;
    }

    private function showContext(int $formatId, object $actor, object $rival, string $fase, int $elo): int
    {
        $rows = DB::select('
            select p.action_key, p.action, p.n, p.pct
            from behavior_priors p
            where p.format_id = ? and p.prior_type = ? and p.elo_bucket = ?
              and p.context->>\'actor\' = ? and p.context->>\'rival\' = ? and p.context->>\'fase\' = ?
            order by p.n desc
            limit ?
        ', [$formatId, BehaviorBuild::PRIOR_TYPE, $elo, $actor->slug, $rival->slug, $fase, (int) $this->option('top')]);

        $this->newLine();

        if ($rows === []) {
            $this->warn(sprintf(
                'No hay muestra suficiente de %s frente a %s en fase %s.',
                $actor->name,
                $rival->name,
                $fase,
            ));
            $this->line('Prueba sin --vs para ver contra que rivales si hay datos.');

            return self::SUCCESS;
        }

        $total = (int) DB::table('behavior_priors')
            ->where('format_id', $formatId)
            ->where('prior_type', BehaviorBuild::PRIOR_TYPE)
            ->where('elo_bucket', $elo)
            ->whereRaw('context->>\'actor\' = ?', [$actor->slug])
            ->whereRaw('context->>\'rival\' = ?', [$rival->slug])
            ->whereRaw('context->>\'fase\' = ?', [$fase])
            ->sum('n');

        $this->line(sprintf(
            '%s con %s en el campo · fase %s · %d decisiones observadas',
            $actor->name,
            $rival->name,
            $fase,
            $total,
        ));
        $this->newLine();

        $moveNames = DB::table('moves')->pluck('name', 'slug');

        $this->table(
            ['Que hace', 'N', '%', 'Intervalo 95%'],
            array_map(function ($row) use ($moveNames, $total) {
                [$low, $high] = Proportion::wilson((int) $row->n, $total);

                return [
                    $this->describe($row->action_key, $moveNames),
                    $row->n,
                    number_format((float) $row->pct, 1).'%',
                    sprintf('%.1f - %.1f%%', $low * 100, $high * 100),
                ];
            }, $rows),
        );

        $this->line('Solo cuenta lo que el jugador eligio: no entran los relevos obligados ni los turnos en que no pudo actuar.');
        $this->line('Fuente: replays publicos de Pokemon Showdown, no el ladder de Pokemon Champions.');

        return self::SUCCESS;
    }

    private function describe(string $actionKey, $moveNames): string
    {
        if ($actionKey === 'switch') {
            return 'Cambiar';
        }

        $slug = substr($actionKey, 5);

        return $moveNames[$slug] ?? $slug;
    }

    private function nameOf(?string $slug): string
    {
        return DB::table('species')->where('slug', $slug)->value('name') ?? (string) $slug;
    }

    private function findSpecies(string $value): ?object
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return DB::table('species')
            ->where('slug', ShowdownLogParser::toId($value))
            ->orWhereRaw('lower(name) = ?', [mb_strtolower($value)])
            ->first();
    }
}
