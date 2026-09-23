<?php

use App\Domain\Build\MovePower;
use App\Domain\Build\FieldEffects;
use App\Domain\Build\TypeChart;

function movimiento(array $campos = []): object
{
    return (object) array_merge([
        'slug' => 'test',
        'type' => 'Water',
        'category' => 'Special',
        'power' => 80,
        'accuracy' => 100,
        'target' => 'normal',
        'flags' => '{}',
        'secondary' => null,
        'priority' => 0,
    ], $campos);
}

it('sube los movimientos pulso con Mega Launcher y no toca a los demas', function () {
    $power = new MovePower(new TypeChart(), new FieldEffects());

    $pulso = $power->score(movimiento(['type' => 'Dragon', 'power' => 85, 'flags' => '{"pulse":1}']), ['Water'], ['megalauncher']);
    $otro = $power->score(movimiento(['type' => 'Ice', 'power' => 90]), ['Water'], ['megalauncher']);

    expect($pulso['potencia'])->toBe(128)
        ->and($pulso['habilidad'])->toBe('megalauncher')
        ->and($otro['potencia'])->toBe(90)
        ->and($otro['habilidad'])->toBeNull();
});

it('aplica el STAB y lo dobla con Adaptabilidad', function () {
    $power = new MovePower(new TypeChart(), new FieldEffects());

    $normal = $power->score(movimiento(), ['Water'], []);
    $adaptado = $power->score(movimiento(), ['Water'], ['adaptability']);

    expect($normal['efectiva'])->toBe(120.0)
        ->and($adaptado['efectiva'])->toBe(160.0);
});

it('penaliza la precision y premia el area', function () {
    $power = new MovePower(new TypeChart(), new FieldEffects());

    $falla = $power->score(movimiento(['accuracy' => 70]), ['Grass'], []);
    $area = $power->score(movimiento(['target' => 'allAdjacentFoes']), ['Grass'], []);
    $infalible = $power->score(movimiento(['accuracy' => null]), ['Grass'], []);

    expect($falla['efectiva'])->toBe(56.0)
        ->and($area['efectiva'])->toBe(120.0)
        ->and($infalible['infalible'])->toBeTrue();
});

it('marca los que golpean al companero', function () {
    $power = new MovePower(new TypeChart(), new FieldEffects());

    expect($power->score(movimiento(['target' => 'allAdjacent']), ['Grass'], [])['golpea_aliado'])->toBeTrue()
        ->and($power->score(movimiento(['target' => 'allAdjacentFoes']), ['Grass'], [])['golpea_aliado'])->toBeFalse();
});

it('descarta lo que no se puede usar en un turno normal', function (array $campos) {
    expect((new MovePower(new TypeChart(), new FieldEffects()))->score(movimiento($campos), ['Grass'], []))->toBeNull();
})->with([
    [['flags' => '{"recharge":1}']],
    [['flags' => '{"charge":1}']],
    [['priority' => -3]],
    [['power' => 0]],
    [['power' => null]],
]);

it('Technician solo sube lo flojo', function () {
    $power = new MovePower(new TypeChart(), new FieldEffects());

    expect($power->score(movimiento(['power' => 60]), ['Grass'], ['technician'])['potencia'])->toBe(90)
        ->and($power->score(movimiento(['power' => 70]), ['Grass'], ['technician'])['potencia'])->toBe(70);
});
