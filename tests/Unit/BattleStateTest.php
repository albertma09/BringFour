<?php

use App\Domain\Replays\BattleState;

it('recuerda quien ocupa cada hueco y con cuanta vida', function () {
    $state = new BattleState();
    $state->enter('p1a', 'sneasler', 100);
    $state->setHp('p1a', 62);

    expect($state->speciesAt('p1a'))->toBe('sneasler')
        ->and($state->hpAt('p1a'))->toBe(62)
        ->and($state->speciesAt('p2b'))->toBeNull();
});

it('no inventa un hueco al cambiar de forma', function () {
    $state = new BattleState();
    $state->changeForme('p1a', 'emboarmega');

    expect($state->speciesAt('p1a'))->toBeNull();
});

it('cambia de forma sin tocar la vida', function () {
    $state = new BattleState();
    $state->enter('p1b', 'emboar', 31);
    $state->changeForme('p1b', 'emboarmega');

    expect($state->speciesAt('p1b'))->toBe('emboarmega')
        ->and($state->hpAt('p1b'))->toBe(31);
});

it('saca del campo a quien se debilita', function () {
    $state = new BattleState();
    $state->enter('p1a', 'sneasler', 100);
    $state->enter('p1b', 'rillaboom', 40);
    $state->faint('p1b');

    expect($state->activesOf('p1'))->toBe(['sneasler'])
        ->and($state->speciesAt('p1b'))->toBe('rillaboom');
});

it('separa el terreno del resto de efectos de campo', function () {
    $state = new BattleState();
    $state->startField('grassyterrain');
    $state->startField('trickroom');

    $snapshot = $state->snapshot();

    expect($snapshot['terreno'])->toBe('grassyterrain')
        ->and($snapshot['campo'])->toBe(['trickroom']);

    $state->endField('grassyterrain');

    expect($state->snapshot()['terreno'])->toBeNull();
});

it('guarda los efectos de cada lado por separado', function () {
    $state = new BattleState();
    $state->startSide('p1', 'tailwind');
    $state->startSide('p2', 'reflect');
    $state->endSide('p2', 'reflect');

    $snapshot = $state->snapshot();

    expect($snapshot['lados']['p1'])->toBe(['tailwind'])
        ->and($snapshot['lados']['p2'])->toBe([]);
});

it('trata el clima none como ausencia de clima', function () {
    $state = new BattleState();
    $state->setWeather('raindance');
    expect($state->snapshot()['clima'])->toBe('raindance');

    $state->setWeather('none');
    expect($state->snapshot()['clima'])->toBeNull();
});
