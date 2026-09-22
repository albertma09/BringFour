<?php

use App\Domain\Replays\ShowdownLogParser;
use Illuminate\Support\Facades\DB;

function reveals($parsed, string $kind): array
{
    $out = [];

    foreach ($parsed->reveals as $reveal) {
        if ($reveal->kind === $kind) {
            $out[$reveal->speciesSlug] = $reveal->valueSlug;
        }
    }

    return $out;
}

it('atribuye la habilidad al que esta en el hueco y no al que acaba de salir', function () {
    $log = implode("\n", [
        '|player|p1|uno|1|1500',
        '|player|p2|dos|1|1500',
        '|poke|p1|Staraptor, L50, M|',
        '|poke|p1|Rillaboom, L50, M|',
        '|poke|p2|Torkoal, L50, M|',
        '|teamsize|p1|2',
        '|teamsize|p2|1',
        '|start|',
        '|switch|p1a: Rillaboom|Rillaboom, L50, M|100/100',
        '|switch|p2a: Torkoal|Torkoal, L50, M|100/100',
        '|turn|1',
        '|switch|p1a: Staraptor|Staraptor, L50, M|100/100',
        '|-ability|p1a: Staraptor|Intimidate|boost',
        '|-unboost|p2a: Torkoal|atk|1',
        '|win|uno',
    ]);

    $habilidades = reveals((new ShowdownLogParser())->parse($log), 'ability');

    expect($habilidades)->toBe(['staraptor' => 'intimidate'])
        ->and($habilidades)->not->toHaveKey('rillaboom');
});

it('lee la habilidad que solo aparece como etiqueta de otra linea', function () {
    $habilidades = reveals(parseFixture('turnos'), 'ability');

    expect($habilidades)->toHaveKey('rillaboom')
        ->and($habilidades['rillaboom'])->toBe('grassysurge');
});

it('atribuye cada objeto a su dueno', function () {
    $objetos = reveals(parseFixture('turnos'), 'item');

    expect($objetos['tinkaton'])->toBe('focussash')
        ->and($objetos['ceruledge'])->toBe('airballoon')
        ->and($objetos['rillaboom'])->toBe('grassyseed');
});

it('no se queda el objeto que le acaban de robar', function () {
    $log = implode("\n", [
        '|player|p1|uno|1|1500',
        '|player|p2|dos|1|1500',
        '|poke|p1|Sneasler, L50, M|',
        '|poke|p2|Incineroar, L50, M|',
        '|teamsize|p1|1',
        '|teamsize|p2|1',
        '|start|',
        '|switch|p1a: Sneasler|Sneasler, L50, M|100/100',
        '|switch|p2a: Incineroar|Incineroar, L50, M|100/100',
        '|turn|1',
        '|move|p2a: Incineroar|Trick|p1a: Sneasler',
        '|-item|p1a: Sneasler|Flame Orb|[from] move: Trick|[of] p2a: Incineroar',
        '|win|uno',
    ]);

    expect(reveals((new ShowdownLogParser())->parse($log), 'item'))->toBe([]);
});

it('guarda una sola revelacion de cada tipo por especie y partida', function () {
    $ids = importFixtures([replayFixture('turnos')]);
    $replayId = (int) array_values($ids)[0];

    $filas = DB::table('replay_reveals')
        ->where('replay_id', $replayId)
        ->get(['side', 'species_id', 'kind']);

    $claves = $filas->map(fn ($f) => "{$f->side}|{$f->species_id}|{$f->kind}")->all();

    expect($filas)->not->toBeEmpty()
        ->and($claves)->toBe(array_values(array_unique($claves)));
});
