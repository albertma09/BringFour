<?php

namespace App\Console\Commands;

use App\Domain\Replays\ParsedReplay;
use App\Domain\Replays\ShowdownLogParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplaysImport extends Command
{
    protected $signature = 'replays:import {--format= : Showdown id de un formato concreto} {--limit=0 : Maximo de replays a importar} {--path= : Directorio de replays crudos}';

    protected $description = 'Parsea los replays crudos y llena replays y replay_teams';

    private const ELO_BUCKETS = [1760, 1630, 1500, 0];

    private array $speciesIds = [];

    private array $baseFormSlug = [];

    public function handle(): int
    {
        $root = $this->option('path') ?: base_path('data/raw/replays');

        if (! is_dir($root)) {
            $this->error("No existe el directorio de replays: {$root}");

            return self::FAILURE;
        }

        $this->loadSpecies();

        $formats = DB::table('formats')
            ->where('process_replays', true)
            ->when($this->option('format'), fn ($q, $id) => $q->where('showdown_id', $id))
            ->get();

        if ($formats->isEmpty()) {
            $this->warn('Ningun formato marcado como procesable.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $parser = new ShowdownLogParser();
        $totals = ['leidos' => 0, 'importados' => 0, 'ya_estaban' => 0, 'descartados' => 0];

        foreach ($formats as $format) {
            $dir = $root.DIRECTORY_SEPARATOR.$format->showdown_id;

            if (! is_dir($dir)) {
                continue;
            }

            $known = DB::table('replays')
                ->where('format_id', $format->id)
                ->pluck('showdown_id')
                ->flip();

            foreach ($this->files($dir) as $file) {
                foreach ($this->records($file) as $record) {
                    $totals['leidos']++;

                    if ($limit > 0 && $totals['importados'] >= $limit) {
                        break 3;
                    }

                    if ($known->has($record['id'])) {
                        $totals['ya_estaban']++;

                        continue;
                    }

                    $parsed = $parser->parse($record['log']);

                    if (! $parsed->isUsable()) {
                        $totals['descartados']++;

                        continue;
                    }

                    $this->store($format, $record, $parsed, basename($file));
                    $totals['importados']++;
                }
            }
        }

        $this->newLine();
        $this->table(array_keys($totals), [array_values($totals)]);

        return self::SUCCESS;
    }

    private function loadSpecies(): void
    {
        $rows = DB::table('species as s')
            ->leftJoin('species as b', 'b.id', '=', 's.base_form_id')
            ->select('s.id', 's.slug', 'b.slug as base_slug')
            ->get();

        foreach ($rows as $row) {
            $this->speciesIds[$row->slug] = $row->id;
            $this->baseFormSlug[$row->slug] = $row->base_slug;
        }
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
        });
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
