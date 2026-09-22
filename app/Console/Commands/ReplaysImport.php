<?php

namespace App\Console\Commands;

use App\Domain\Replays\ParsedReplay;
use App\Domain\Replays\ShowdownLogParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplaysImport extends Command
{
    protected $signature = 'replays:import
        {--format= : Showdown id de un formato concreto}
        {--limit=0 : Maximo de replays a importar}
        {--path= : Directorio de replays crudos}
        {--reparse : Vuelve a parsear turnos y acciones de los replays ya importados}';

    protected $description = 'Parsea los replays crudos y llena replays, replay_teams, replay_turns y replay_actions';

    private const ELO_BUCKETS = [1760, 1630, 1500, 0];

    private const ACTION_CHUNK = 2000;

    private array $speciesIds = [];

    private array $baseFormSlug = [];

    private array $moveIds = [];

    private array $abilityIds = [];

    private array $itemIds = [];

    private array $actionBuffer = [];

    public function handle(): int
    {
        $root = $this->option('path') ?: base_path('data/raw/replays');

        if (! is_dir($root)) {
            $this->error("No existe el directorio de replays: {$root}");

            return self::FAILURE;
        }

        $this->loadCatalogue();

        $formats = DB::table('formats')
            ->where('process_replays', true)
            ->when($this->option('format'), fn ($q, $id) => $q->where('showdown_id', $id))
            ->get();

        if ($formats->isEmpty()) {
            $this->warn('Ningun formato marcado como procesable.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $reparse = (bool) $this->option('reparse');
        $parser = new ShowdownLogParser();
        $totals = ['leidos' => 0, 'importados' => 0, 'reparseados' => 0, 'ya_estaban' => 0, 'descartados' => 0];

        foreach ($formats as $format) {
            $dir = $root.DIRECTORY_SEPARATOR.$format->showdown_id;

            if (! is_dir($dir)) {
                continue;
            }

            $known = DB::table('replays')
                ->where('format_id', $format->id)
                ->pluck('id', 'showdown_id');

            foreach ($this->files($dir) as $file) {
                foreach ($this->records($file) as $record) {
                    $totals['leidos']++;

                    if ($limit > 0 && $totals['importados'] + $totals['reparseados'] >= $limit) {
                        break 3;
                    }

                    $existing = $known->get($record['id']);

                    if ($existing !== null && ! $reparse) {
                        $totals['ya_estaban']++;

                        continue;
                    }

                    $parsed = $parser->parse($record['log']);

                    if (! $parsed->isUsable()) {
                        $totals['descartados']++;

                        continue;
                    }

                    if ($existing !== null) {
                        $this->reparse((int) $existing, $parsed);
                        $totals['reparseados']++;
                    } else {
                        $this->store($format, $record, $parsed, basename($file));
                        $totals['importados']++;
                    }

                    $hechos = $totals['importados'] + $totals['reparseados'];

                    if ($hechos > 0 && $hechos % 500 === 0) {
                        $this->line("  {$hechos} replays procesados");
                    }
                }
            }
        }

        $this->flushActions(true);

        $this->newLine();
        $this->table(array_keys($totals), [array_values($totals)]);

        return self::SUCCESS;
    }

    private function loadCatalogue(): void
    {
        $rows = DB::table('species as s')
            ->leftJoin('species as b', 'b.id', '=', 's.base_form_id')
            ->select('s.id', 's.slug', 'b.slug as base_slug')
            ->get();

        foreach ($rows as $row) {
            $this->speciesIds[$row->slug] = $row->id;
            $this->baseFormSlug[$row->slug] = $row->base_slug;
        }

        $this->moveIds = DB::table('moves')->pluck('id', 'slug')->all();
        $this->abilityIds = DB::table('abilities')->pluck('id', 'slug')->all();
        $this->itemIds = DB::table('items')->pluck('id', 'slug')->all();
    }

    private function files(string $dir): array
    {
        $files = array_merge(
            glob($dir.DIRECTORY_SEPARATOR.'*.jsonl') ?: [],
            glob($dir.DIRECTORY_SEPARATOR.'*.jsonl.gz') ?: [],
        );

        sort($files);

        return $files;
    }

    private function records(string $file): iterable
    {
        $handle = str_ends_with($file, '.gz') ? gzopen($file, 'rb') : fopen($file, 'rb');

        if ($handle === false) {
            return;
        }

        $read = str_ends_with($file, '.gz')
            ? fn ($h) => gzgets($h)
            : fn ($h) => fgets($h);

        while (($line = $read($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $record = json_decode($line, true);

            if (is_array($record) && isset($record['id'], $record['log'])) {
                yield $record;
            }
        }

        str_ends_with($file, '.gz') ? gzclose($handle) : fclose($handle);
    }

    private function store(object $format, array $record, ParsedReplay $parsed, string $sourceFile): void
    {
        DB::transaction(function () use ($format, $record, $parsed, $sourceFile) {
            $ratings = array_filter([$parsed->ratings['p1'], $parsed->ratings['p2']], fn ($r) => $r !== null);

            $replayId = DB::table('replays')->insertGetId([
                'showdown_id' => $record['id'],
                'format_id' => $format->id,
                'regulation_id' => $format->regulation_id,
                'uploaded_at' => now()->setTimestamp($record['uploadtime']),
                'p1_hash' => hash('sha256', (string) $parsed->players['p1']),
                'p2_hash' => hash('sha256', (string) $parsed->players['p2']),
                'p1_rating' => $parsed->ratings['p1'],
                'p2_rating' => $parsed->ratings['p2'],
                'elo_bucket' => count($ratings) === 2 ? $this->bucket(min($ratings)) : null,
                'winner_side' => $parsed->winnerSide,
                'turn_count' => $parsed->turnCount,
                'p1_team_size' => $parsed->teamSize['p1'],
                'p2_team_size' => $parsed->teamSize['p2'],
                'rated' => count($ratings) === 2,
                'open_team_sheets' => $parsed->openTeamSheets,
                'source_file' => $sourceFile,
                'raw_log_sha256' => hash('sha256', $record['log']),
                'parsed_at' => now(),
                'parser_version' => ShowdownLogParser::VERSION,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->storeTeams($replayId, $parsed);
            $this->storeTurns($replayId, $parsed);
            $this->storeReveals($replayId, $parsed);
        });
    }

    private function reparse(int $replayId, ParsedReplay $parsed): void
    {
        DB::transaction(function () use ($replayId, $parsed) {
            DB::table('replay_turns')->where('replay_id', $replayId)->delete();
            DB::table('replay_reveals')->where('replay_id', $replayId)->delete();

            DB::table('replays')->where('id', $replayId)->update([
                'turn_count' => $parsed->turnCount,
                'parsed_at' => now(),
                'parser_version' => ShowdownLogParser::VERSION,
                'updated_at' => now(),
            ]);

            $this->storeTurns($replayId, $parsed);
            $this->storeReveals($replayId, $parsed);
        });
    }

    private function storeReveals(int $replayId, ParsedReplay $parsed): void
    {
        $rows = [];

        foreach ($parsed->reveals as $reveal) {
            $speciesId = $this->lookup($this->speciesIds, $reveal->speciesSlug);
            $valueId = $reveal->kind === 'ability'
                ? $this->lookup($this->abilityIds, $reveal->valueSlug)
                : $this->lookup($this->itemIds, $reveal->valueSlug);

            if ($speciesId === null || $valueId === null) {
                continue;
            }

            $rows[] = [
                'replay_id' => $replayId,
                'side' => $reveal->side,
                'species_id' => $speciesId,
                'kind' => $reveal->kind,
                'ability_id' => $reveal->kind === 'ability' ? $valueId : null,
                'item_id' => $reveal->kind === 'item' ? $valueId : null,
                'turn_no' => $reveal->turnNo,
            ];
        }

        if ($rows !== []) {
            DB::table('replay_reveals')->insert($rows);
        }
    }

    private function storeTeams(int $replayId, ParsedReplay $parsed): void
    {
        $rows = [];

        foreach (['p1', 'p2'] as $side) {
            $brought = $parsed->broughtSlugs($side);
            $leads = $parsed->leadSlugs($side);

            foreach ($parsed->preview[$side] as $position => $slug) {
                $speciesId = $this->speciesIds[$slug] ?? null;

                if ($speciesId === null) {
                    continue;
                }

                $rows[] = [
                    'replay_id' => $replayId,
                    'side' => $side,
                    'species_id' => $speciesId,
                    'preview_position' => $position,
                    'brought' => $this->matches($slug, $brought),
                    'lead' => $this->matches($slug, $leads),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            DB::table('replay_teams')->insert($rows);
        }
    }

    private function storeTurns(int $replayId, ParsedReplay $parsed): void
    {
        if ($parsed->turns === []) {
            return;
        }

        $now = now();
        $turnRows = [];

        foreach ($parsed->turns as $turn) {
            $turnRows[] = [
                'replay_id' => $replayId,
                'turn_no' => $turn->number,
                'decision_seconds' => $turn->decisionSeconds,
                'field_state' => json_encode($turn->fieldState, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('replay_turns')->insert($turnRows);

        $turnIds = DB::table('replay_turns')->where('replay_id', $replayId)->pluck('id', 'turn_no');

        foreach ($parsed->turns as $turn) {
            $turnId = $turnIds->get($turn->number);

            if ($turnId === null) {
                continue;
            }

            foreach ($turn->actions as $action) {
                $this->actionBuffer[] = [
                    'replay_turn_id' => $turnId,
                    'side' => $action->side,
                    'slot' => $action->slot,
                    'action_type' => $action->type,
                    'forced' => $action->forced,
                    'reason' => $action->reason,
                    'actor_species_id' => $this->lookup($this->speciesIds, $action->actorSlug),
                    'switch_in_species_id' => $this->lookup($this->speciesIds, $action->switchInSlug),
                    'actor_hp_pct' => $action->actorHpPct,
                    'move_id' => $this->lookup($this->moveIds, $action->moveSlug),
                    'target_side' => $action->targetSide,
                    'target_slot' => $action->targetSlot,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->flushActions(true);
    }

    private function flushActions(bool $force): void
    {
        if ($this->actionBuffer === [] || (! $force && count($this->actionBuffer) < self::ACTION_CHUNK)) {
            return;
        }

        foreach (array_chunk($this->actionBuffer, self::ACTION_CHUNK) as $chunk) {
            DB::table('replay_actions')->insert($chunk);
        }

        $this->actionBuffer = [];
    }

    private function lookup(array $map, ?string $slug): ?int
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return $map[$slug] ?? null;
    }

    private function matches(string $previewSlug, array $actualSlugs): bool
    {
        foreach ($actualSlugs as $actual) {
            if ($actual === $previewSlug) {
                return true;
            }

            if (($this->baseFormSlug[$actual] ?? null) === $previewSlug) {
                return true;
            }
        }

        return false;
    }

    private function bucket(int $rating): int
    {
        foreach (self::ELO_BUCKETS as $floor) {
            if ($rating >= $floor) {
                return $floor;
            }
        }

        return 0;
    }
}
