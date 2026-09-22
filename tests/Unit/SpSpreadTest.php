<?php

use App\Domain\Stats\SpSpread;

$spread = fn (array $overrides = []) => array_merge(
    ['hp' => 0, 'atk' => 0, 'def' => 0, 'spa' => 0, 'spd' => 0, 'spe' => 0],
    $overrides,
);

it('acepta un spread de exactamente 66 SP', function () use ($spread) {
    expect(SpSpread::isValid($spread(['hp' => 2, 'spa' => 32, 'spe' => 32])))->toBeTrue();
});

it('acepta un spread incompleto porque el builder permite trabajo en curso', function () use ($spread) {
    $sp = $spread(['atk' => 10]);

    expect(SpSpread::isValid($sp))->toBeTrue()
        ->and(SpSpread::remaining($sp))->toBe(56);
});

it('rechaza mas de 66 SP en total', function () use ($spread) {
    $sp = $spread(['hp' => 32, 'atk' => 32, 'def' => 32]);

    expect(SpSpread::isValid($sp))->toBeFalse()
        ->and(SpSpread::errors($sp))->toContain('el total de SP es 96, el maximo es 66');
});

it('rechaza mas de 32 SP en una sola estadistica', function () use ($spread) {
    $sp = $spread(['atk' => 33]);

    expect(SpSpread::isValid($sp))->toBeFalse()
        ->and(SpSpread::errors($sp)[0])->toContain('excede el tope de 32');
});

it('rechaza valores negativos', function () use ($spread) {
    expect(SpSpread::isValid($spread(['atk' => -1])))->toBeFalse();
});

it('rechaza estadisticas desconocidas', function () use ($spread) {
    expect(SpSpread::errors($spread(['luck' => 5])))->toContain('sp.luck no es una estadistica valida');
});

it('impide el clasico 252/252 en SP porque el tope lo prohibe', function () use ($spread) {
    expect(SpSpread::isValid($spread(['atk' => 32, 'spe' => 32, 'hp' => 2])))->toBeTrue()
        ->and(SpSpread::total($spread(['atk' => 32, 'spe' => 32, 'hp' => 2])))->toBe(66);
});

it('normaliza rellenando las estadisticas ausentes', function () {
    expect(SpSpread::normalize(['atk' => 4]))
        ->toBe(['hp' => 0, 'atk' => 4, 'def' => 0, 'spa' => 0, 'spd' => 0, 'spe' => 0]);
});
