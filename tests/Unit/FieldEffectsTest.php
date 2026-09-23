<?php

use App\Domain\Build\FieldEffects;

function mov(string $slug, string $tipo = 'Water', array $extra = []): object
{
    return (object) array_merge([
        'slug' => $slug,
        'type' => $tipo,
        'category' => 'Special',
        'power' => 100,
        'accuracy' => 100,
        'target' => 'normal',
        'flags' => '{}',
        'secondary' => null,
        'priority' => 0,
    ], $extra);
}

it('sube y baja el golpe segun el clima', function () {
    $field = new FieldEffects();

    expect($field->apply(mov('surf', 'Water'), ['Water'], [], 'raindance', null)['x'])->toBe(1.5)
        ->and($field->apply(mov('surf', 'Water'), ['Water'], [], 'sunnyday', null)['x'])->toBe(0.5)
        ->and($field->apply(mov('flamethrower', 'Fire'), ['Fire'], [], 'sunnyday', null)['x'])->toBe(1.5)
        ->and($field->apply(mov('flamethrower', 'Fire'), ['Fire'], [], 'raindance', null)['x'])->toBe(0.5);
});

it('el terreno sube su tipo solo a quien pisa el suelo', function () {
    $field = new FieldEffects();

    expect($field->apply(mov('energyball', 'Grass'), ['Grass'], [], null, 'grassyterrain')['x'])->toBe(1.3)
        ->and($field->apply(mov('energyball', 'Grass'), ['Flying'], [], null, 'grassyterrain')['x'])->toBe(1.0)
        ->and($field->apply(mov('energyball', 'Grass'), ['Grass'], ['levitate'], null, 'grassyterrain')['x'])->toBe(1.0);
});

it('el Campo de Hierba parte por la mitad los temblores', function () {
    expect((new FieldEffects())->apply(mov('earthquake', 'Ground'), ['Ground'], [], null, 'grassyterrain')['x'])->toBe(0.5);
});

it('Hierba Parasita solo tiene prioridad con su campo', function () {
    $field = new FieldEffects();
    $movimiento = mov('grassyglide', 'Grass', ['power' => 55]);

    expect($field->apply($movimiento, ['Grass'], [], null, 'grassyterrain')['prioridad'])->toBe(1)
        ->and($field->apply($movimiento, ['Grass'], [], null, 'psychicterrain')['prioridad'])->toBe(0)
        ->and($field->apply($movimiento, ['Grass'], [], null, null)['prioridad'])->toBe(0);
});

it('la lluvia y la nevada arreglan la precision', function () {
    $field = new FieldEffects();

    expect($field->apply(mov('thunder', 'Electric', ['accuracy' => 70]), ['Electric'], [], 'raindance', null)['precision'])->toBe(100)
        ->and($field->apply(mov('blizzard', 'Ice', ['accuracy' => 70]), ['Ice'], [], 'snowscape', null)['precision'])->toBe(100)
        ->and($field->apply(mov('thunder', 'Electric', ['accuracy' => 70]), ['Electric'], [], null, null)['precision'])->toBeNull();
});

it('Bola Clima y Pulso de Campo cambian de tipo', function () {
    $field = new FieldEffects();

    expect($field->apply(mov('weatherball', 'Normal'), ['Normal'], [], 'raindance', null)['tipo'])->toBe('Water')
        ->and($field->apply(mov('terrainpulse', 'Normal'), ['Normal'], [], null, 'electricterrain')['tipo'])->toBe('Electric')
        ->and($field->apply(mov('weatherball', 'Normal'), ['Normal'], [], null, null)['tipo'])->toBeNull();
});

it('el Rayo Solar se salta la carga al sol', function () {
    $field = new FieldEffects();

    expect($field->apply(mov('solarbeam', 'Grass'), ['Grass'], [], 'sunnyday', null)['sin_carga'])->toBeTrue()
        ->and($field->apply(mov('solarbeam', 'Grass'), ['Grass'], [], null, null)['sin_carga'])->toBeFalse();
});

it('sabe quien pone cada campo', function (string $habilidad, string $clase, string $campo) {
    expect((new FieldEffects())->sets([$habilidad]))->toMatchArray(['tipo' => $clase, 'campo' => $campo]);
})->with([
    ['drizzle', 'clima', 'raindance'],
    ['drought', 'clima', 'sunnyday'],
    ['sandstream', 'clima', 'sandstorm'],
    ['snowwarning', 'clima', 'snowscape'],
    ['grassysurge', 'terreno', 'grassyterrain'],
    ['psychicsurge', 'terreno', 'psychicterrain'],
]);

it('dobla la velocidad solo con su clima', function () {
    $field = new FieldEffects();

    expect($field->speedMultiplier(['swiftswim'], 'raindance')['x'])->toBe(2.0)
        ->and($field->speedMultiplier(['swiftswim'], 'sunnyday')['x'])->toBe(1.0)
        ->and($field->speedMultiplier(['swiftswim'], null)['x'])->toBe(1.0)
        ->and($field->speedMultiplier(['chlorophyll'], 'sunnyday')['x'])->toBe(2.0)
        ->and($field->needsWeather(['sandrush']))->toBe('sandstorm');
});

it('sin campo no toca nada', function () {
    $sin = (new FieldEffects())->apply(mov('surf', 'Water'), ['Water'], [], null, null);

    expect($sin['x'])->toBe(1.0)
        ->and($sin['prioridad'])->toBe(0)
        ->and($sin['precision'])->toBeNull()
        ->and($sin['tipo'])->toBeNull()
        ->and($sin['campo'])->toBeNull();
});

it('el Campo Psiquico bloquea la prioridad', function () {
    $field = new FieldEffects();

    expect($field->blocksPriority('psychicterrain'))->toBeTrue()
        ->and($field->blocksPriority('grassyterrain'))->toBeFalse()
        ->and($field->blocksPriority(null))->toBeFalse();
});
