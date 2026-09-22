<?php

use App\Domain\Stats\Proportion;

it('calcula el intervalo de Wilson de una proporcion conocida', function () {
    [$low, $high] = Proportion::wilson(27, 30);

    expect(round($low, 3))->toBe(0.744)
        ->and(round($high, 3))->toBe(0.965);
});

it('nunca se sale del rango 0-1 en los extremos', function () {
    [$lowCero, $highCero] = Proportion::wilson(0, 12);
    [$lowTodo, $highTodo] = Proportion::wilson(12, 12);

    expect($lowCero)->toBe(0.0)
        ->and($highCero)->toBeLessThan(1.0)
        ->and($highTodo)->toBe(1.0)
        ->and($lowTodo)->toBeGreaterThan(0.0);
});

it('se estrecha al crecer la muestra con la misma proporcion', function () {
    [$lowPoca, $highPoca] = Proportion::wilson(21, 30);
    [$lowMucha, $highMucha] = Proportion::wilson(700, 1000);

    expect($highMucha - $lowMucha)->toBeLessThan($highPoca - $lowPoca);
});

it('no distingue del baseline una diferencia que cabe en el intervalo', function () {
    expect(Proportion::differsFrom(21, 30, 0.68))->toBeFalse()
        ->and(Proportion::differsFrom(21, 30, 0.30))->toBeTrue();
});

it('con la misma proporcion solo detecta el cambio cuando hay muestra', function () {
    expect(Proportion::differsFrom(24, 30, 0.68))->toBeFalse()
        ->and(Proportion::differsFrom(800, 1000, 0.68))->toBeTrue();
});

it('no afirma nada sin observaciones', function () {
    expect(Proportion::differsFrom(0, 0, 0.5))->toBeFalse()
        ->and(Proportion::wilson(0, 0))->toBe([0.0, 1.0]);
});
