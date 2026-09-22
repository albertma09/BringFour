<?php

use Illuminate\Support\Facades\DB;

it('lee el Team Preview completo de ambos lados', function () {
    $parsed = parseFixture('normal');

    expect($parsed->preview['p1'])->toHaveCount(6)
        ->and($parsed->preview['p2'])->toHaveCount(6)
        ->and($parsed->gameType)->toBe('doubles')
        ->and($parsed->isUsable())->toBeTrue();
});

it('detecta los 4 traidos y los 2 leads en una partida completa', function () {
    $parsed = parseFixture('normal');

    foreach (['p1', 'p2'] as $side) {
        expect($parsed->teamSize[$side])->toBe(4)
            ->and($parsed->leadSlugs($side))->toHaveCount(2);
    }
});

it('resuelve el ganador y el numero de turnos', function () {
    $parsed = parseFixture('normal');

    expect($parsed->winnerSide)->toBeIn(['p1', 'p2'])
        ->and($parsed->turnCount)->toBeGreaterThan(5);
});

it('conserva el Team Preview aunque la partida acabe enseguida', function () {
    $parsed = parseFixture('partida-corta');

    expect($parsed->preview['p1'])->toHaveCount(6)
        ->and($parsed->preview['p2'])->toHaveCount(6)
        ->and($parsed->isUsable())->toBeTrue()
        ->and($parsed->turnCount)->toBeLessThan(3);
});

it('cuenta la forma mega como su forma base al marcar traido', function () {
    $ids = importFixtures([replayFixture('con-mega')]);

    $rows = DB::table('replay_teams as t')
        ->join('species as s', 's.id', '=', 't.species_id')
        ->where('t.replay_id', $ids['gen9championsvgc2026regmc-2685633925'])
        ->where('t.side', 'p1')
        ->orderBy('t.preview_position')
        ->pluck('t.brought', 's.name');

    expect($rows['Emboar'])->toBeTrue()
        ->and($rows['Sinistcha'])->toBeFalse()
        ->and($rows->filter()->count())->toBe(4);
});

it('guarda el tamano de equipo declarado en una partida que se jugo', function () {
    $normal = replayFixture('normal');
    importFixtures([$normal]);

    $row = DB::table('replays')->where('showdown_id', $normal['id'])->first();

    expect($row->p1_team_size)->toBe(4)->and($row->p2_team_size)->toBe(4);
});

it('deja el tamano de equipo a null si se abandono durante el Team Preview', function () {
    $corta = replayFixture('partida-corta');
    importFixtures([$corta]);

    $row = DB::table('replays')->where('showdown_id', $corta['id'])->first();

    expect($row->p1_team_size)->toBeNull()
        ->and($row->p2_team_size)->toBeNull()
        ->and($row->turn_count)->toBe(0);
});

it('no cuenta como completa una partida que acabo antes de ver a todo el equipo', function () {
    importFixtures([replayFixture('partida-corta')]);

    $visto = DB::table('replay_teams')->where('side', 'p1')->where('brought', true)->count();

    expect($visto)->toBeLessThan(4);
});

it('no duplica al reimportar', function () {
    $records = [replayFixture('normal'), replayFixture('con-mega')];
    $root = fixtureDir($records);

    $this->artisan('replays:import', ['--path' => $root])->assertSuccessful();
    $primera = DB::table('replay_teams')->count();

    $this->artisan('replays:import', ['--path' => $root])->assertSuccessful();

    expect(DB::table('replays')->count())->toBe(2)
        ->and(DB::table('replay_teams')->count())->toBe($primera);
});

it('nunca guarda el nombre del jugador en claro', function () {
    $record = replayFixture('normal');
    importFixtures([$record]);

    $row = DB::table('replays')->where('showdown_id', $record['id'])->first();
    $nombres = $record['players'];

    expect($row->p1_hash)->toHaveLength(64)
        ->and($row->p2_hash)->toHaveLength(64)
        ->and($row->p1_hash)->not->toBe($nombres[0])
        ->and($row->p2_hash)->not->toBe($nombres[1])
        ->and($row->p1_hash)->toBe(hash('sha256', $nombres[0]));
});
