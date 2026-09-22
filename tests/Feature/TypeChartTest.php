<?php

use App\Domain\Build\TypeChart;

it('acierta los casos conocidos de la tabla de tipos', function (string $atacante, array $defensores, float $esperado) {
    expect(app(TypeChart::class)->multiplier($atacante, $defensores))->toBe($esperado);
})->with([
    ['water', ['Fire'], 2.0],
    ['fire', ['Water'], 0.5],
    ['normal', ['Ghost'], 0.0],
    ['ground', ['Flying'], 0.0],
    ['electric', ['Water', 'Flying'], 4.0],
    ['grass', ['Water', 'Ground'], 4.0],
    ['fighting', ['Ghost', 'Dark'], 0.0],
    ['ice', ['Dragon', 'Flying'], 4.0],
    ['fire', ['Water', 'Dragon'], 0.25],
]);

it('lista las debilidades y resistencias de un tipo doble', function () {
    $chart = app(TypeChart::class);

    expect($chart->weaknesses(['Water']))->toHaveKeys(['electric', 'grass'])
        ->and($chart->weaknesses(['Water']))->toHaveCount(2)
        ->and($chart->resistances(['Water']))->toHaveKeys(['fire', 'water', 'ice', 'steel']);
});

it('tiene los dieciocho tipos jugables', function () {
    expect(app(TypeChart::class)->types())->toHaveCount(18);
});
