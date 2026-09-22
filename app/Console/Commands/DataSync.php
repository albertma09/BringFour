<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DataSync extends Command
{
    protected $signature = 'data:sync {--path= : Directorio de datos generado por ingest}';

    protected $description = 'Carga el catalogo y la legalidad por regulacion desde los JSON de ingest';

    private string $dataPath;

    public function handle(): int
    {
        $this->dataPath = $this->option('path') ?: base_path('data');

        if (! is_dir($this->dataPath)) {
            $this->error("No existe el directorio de datos: {$this->dataPath}");
            $this->line('Ejecuta primero: node ingest/showdown-data.mjs');

            return self::FAILURE;
        }

        DB::transaction(function () {
            $this->syncCatalogue();
            $this->syncSpanishNames();
            $this->syncSprites();
            $this->syncRegulations();
        });

        $this->newLine();
        $this->info('Sincronizacion completada.');
        $this->table(
            ['tabla', 'filas'],
            collect(['species', 'moves', 'abilities', 'items', 'alignments', 'regulations', 'formats', 'legality', 'learnsets'])
                ->map(fn (string $table) => [$table, DB::table($table)->count()])
                ->all(),
        );

        return self::SUCCESS;
    }

    private function read(string $relative): array
    {
        $path = $this->dataPath.DIRECTORY_SEPARATOR.$relative;

        if (! is_file($path)) {
            throw new RuntimeException("Falta el fichero de datos: {$relative}");
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function now(): string
    {
        return now()->toDateTimeString();
    }

    private function syncSprites(): void
    {
        $sprites = $this->read('sprites.json');
        $values = [];
        $bindings = [];

        foreach ($sprites as $slug => $entry) {
            $values[] = '(?, ?, ?)';
            $bindings[] = $slug;
            $bindings[] = $entry['sprite'];
            $bindings[] = $entry['stone'] ?? null;
        }

        foreach (array_chunk($values, 400) as $index => $chunk) {
            $slice = array_slice($bindings, $index * 400 * 3, count($chunk) * 3);

            DB::statement(
                'update species as t
                 set sprite_file = v.sprite_file, sprite_stone_slug = v.stone
                 from (values '.implode(', ', $chunk).') as v(slug, sprite_file, stone)
                 where t.slug = v.slug',
                $slice,
            );
        }

        $this->line('sprites: '.count($sprites));
    }

    private function syncSpanishNames(): void
    {
        $names = $this->read('names.es.json');

        foreach (['species', 'moves', 'abilities', 'items'] as $table) {
            $pairs = $names[$table] ?? [];

            if ($pairs === []) {
                continue;
            }

            foreach (array_chunk($pairs, 400, true) as $chunk) {
                $values = [];
                $bindings = [];

                foreach ($chunk as $slug => $nameEs) {
                    $values[] = '(?, ?)';
                    $bindings[] = $slug;
                    $bindings[] = $nameEs;
                }

                DB::statement(
                    "update {$table} as t set name_es = v.name_es
                     from (values ".implode(', ', $values).') as v(slug, name_es)
                     where t.slug = v.slug',
                    $bindings,
                );
            }

            $this->line("{$table} en espanol: ".count($pairs));
        }
    }

    private function syncCatalogue(): void
    {
        $timestamp = $this->now();

        $species = $this->read('species.json');
        DB::table('species')->upsert(
            collect($species)->map(fn (array $row) => [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'sprite_id' => $row['spriteId'],
                'national_dex' => $row['nationalDex'],
                'types' => json_encode($row['types']),
                'base_stats' => json_encode($row['baseStats']),
                'abilities' => json_encode($row['abilities']),
                'is_mega' => $row['isMega'],
                'is_buildable' => $row['isBuildable'],
                'required_item_slug' => $row['requiredItemSlug'],
                'weight_kg' => $row['weightKg'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all(),
            ['slug'],
            ['name', 'sprite_id', 'national_dex', 'types', 'base_stats', 'abilities', 'is_mega', 'is_buildable', 'required_item_slug', 'weight_kg', 'updated_at'],
        );

        $speciesIds = DB::table('species')->pluck('id', 'slug');
        foreach (collect($species)->whereNotNull('baseFormSlug')->chunk(200) as $chunk) {
            foreach ($chunk as $row) {
                DB::table('species')
                    ->where('slug', $row['slug'])
                    ->update(['base_form_id' => $speciesIds[$row['baseFormSlug']] ?? null]);
            }
        }
        $this->line('species: '.count($species));

        $moves = $this->read('moves.json');
        DB::table('moves')->upsert(
            collect($moves)->map(fn (array $row) => [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'type' => $row['type'],
                'category' => $row['category'],
                'power' => $row['power'],
                'accuracy' => $row['accuracy'],
                'pp' => $row['pp'],
                'priority' => $row['priority'],
                'target' => $row['target'],
                'flags' => json_encode($row['flags']),
                'secondary' => json_encode($row['secondary']),
                'description' => $row['description'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all(),
            ['slug'],
            ['name', 'type', 'category', 'power', 'accuracy', 'pp', 'priority', 'target', 'flags', 'secondary', 'description', 'updated_at'],
        );
        $this->line('moves: '.count($moves));

        $abilities = $this->read('abilities.json');
        DB::table('abilities')->upsert(
            collect($abilities)->map(fn (array $row) => [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'description' => $row['description'],
                'effect' => json_encode($row['effect']),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all(),
            ['slug'],
            ['name', 'description', 'effect', 'updated_at'],
        );
        $this->line('abilities: '.count($abilities));

        $items = $this->read('items.json');
        DB::table('items')->upsert(
            collect($items)->map(fn (array $row) => [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'description' => $row['description'],
                'effect' => json_encode($row['effect']),
                'is_mega_stone' => $row['isMegaStone'],
                'mega_evolutions' => json_encode($row['megaEvolutions'] ?? null),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all(),
            ['slug'],
            ['name', 'description', 'effect', 'is_mega_stone', 'mega_evolutions', 'updated_at'],
        );
        $this->line('items: '.count($items));

        $alignments = $this->read('alignments.json');
        DB::table('alignments')->upsert(
            collect($alignments)->map(fn (array $row) => [
                'slug' => $row['slug'],
                'name' => $row['name'],
                'plus_stat' => $row['plusStat'],
                'minus_stat' => $row['minusStat'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all(),
            ['slug'],
            ['name', 'plus_stat', 'minus_stat', 'updated_at'],
        );
        $this->line('alignments: '.count($alignments));
    }

    private function syncRegulations(): void
    {
        $timestamp = $this->now();
        $regulations = $this->read('regulations.json');

        DB::table('regulations')->upsert(
            collect($regulations)->map(fn (array $row) => [
                'code' => $row['code'],
                'name' => $row['name'],
                'starts_at' => $row['startsAt'],
                'ends_at' => $row['endsAt'],
                'is_active' => $row['isActive'],
                'source_version' => $row['mod'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all(),
            ['code'],
            ['name', 'starts_at', 'ends_at', 'is_active', 'source_version', 'updated_at'],
        );

        $regulationIds = DB::table('regulations')->pluck('id', 'code');
        $entityIds = [
            'species' => DB::table('species')->pluck('id', 'slug'),
            'moves' => DB::table('moves')->pluck('id', 'slug'),
            'abilities' => DB::table('abilities')->pluck('id', 'slug'),
            'items' => DB::table('items')->pluck('id', 'slug'),
        ];

        foreach ($regulations as $regulation) {
            $regulationId = $regulationIds[$regulation['code']];

            DB::table('formats')->upsert(
                collect($regulation['formats'])->map(fn (array $format) => [
                    'regulation_id' => $regulationId,
                    'slug' => $format['slug'],
                    'showdown_id' => $format['showdownId'],
                    'battle_type' => $format['battleType'],
                    'team_size_min' => $format['teamSizeMin'],
                    'team_size_max' => $format['teamSizeMax'],
                    'bring_count' => $format['bringCount'],
                    'best_of_three' => $format['bestOfThree'],
                    'collect_replays' => $format['collectReplays'],
                    'process_replays' => $format['processReplays'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all(),
                ['showdown_id'],
                ['regulation_id', 'slug', 'battle_type', 'team_size_min', 'team_size_max', 'bring_count', 'best_of_three', 'collect_replays', 'process_replays', 'updated_at'],
            );

            $legality = $this->read($regulation['code'].'/legality.json');
            DB::table('legality')->where('regulation_id', $regulationId)->delete();

            foreach ($legality as $entityType => $slugs) {
                $rows = collect($slugs)
                    ->map(fn (string $slug) => [
                        'regulation_id' => $regulationId,
                        'entity_type' => $entityType,
                        'entity_id' => $entityIds[$entityType][$slug] ?? null,
                        'is_legal' => true,
                        'restriction' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])
                    ->whereNotNull('entity_id');

                foreach ($rows->chunk(2000) as $chunk) {
                    DB::table('legality')->insert($chunk->values()->all());
                }
            }

            $learnsets = $this->read($regulation['code'].'/learnsets.json');
            DB::table('learnsets')->where('regulation_id', $regulationId)->delete();

            $buffer = [];
            foreach ($learnsets as $speciesSlug => $moveSlugs) {
                $speciesId = $entityIds['species'][$speciesSlug] ?? null;
                if (! $speciesId) {
                    continue;
                }

                foreach ($moveSlugs as $moveSlug) {
                    $moveId = $entityIds['moves'][$moveSlug] ?? null;
                    if (! $moveId) {
                        continue;
                    }

                    $buffer[] = [
                        'regulation_id' => $regulationId,
                        'species_id' => $speciesId,
                        'move_id' => $moveId,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    if (count($buffer) >= 5000) {
                        DB::table('learnsets')->insert($buffer);
                        $buffer = [];
                    }
                }
            }

            if ($buffer !== []) {
                DB::table('learnsets')->insert($buffer);
            }

            $this->line("{$regulation['code']}: legalidad y learnsets cargados");
        }
    }
}
