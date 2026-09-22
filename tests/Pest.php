<?php

use App\Domain\Replays\ShowdownLogParser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseTransactions::class)->in('Feature');

function replayFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/replays/{$name}.json")), true);
}

function parseFixture(string $name)
{
    return (new ShowdownLogParser())->parse(replayFixture($name)['log']);
}

function fixtureDir(array $records): string
{
    $root = sys_get_temp_dir().'/bringfour-test-'.bin2hex(random_bytes(4));
    $dir = $root.'/gen9championsvgc2026regmc';
    mkdir($dir, 0777, true);

    $lines = array_map(fn ($r) => json_encode($r), $records);
    file_put_contents($dir.'/2026-09-22.jsonl', implode("\n", $lines)."\n");

    return $root;
}

function importFixtures(array $records): array
{
    $root = fixtureDir($records);
    test()->artisan('replays:import', ['--path' => $root])->assertSuccessful();

    return DB::table('replays')->pluck('id', 'showdown_id')->all();
}
