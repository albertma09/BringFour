<?php

use App\Domain\Stats\StatCalculator;

beforeEach(function () {
    $this->calc = new StatCalculator();
});

it('calcula HP como base + 75 + SP', function (int $base, int $sp, int $expected) {
    expect($this->calc->stat('hp', $base, $sp))->toBe($expected);
})->with([
    'Incineroar sin SP' => [95, 0, 170],
    'Incineroar con 32 SP' => [95, 32, 202],
    'Rillaboom sin SP' => [100, 0, 175],
    'base minima' => [1, 0, 76],
]);

it('ignora el alignment en HP', function () {
    expect($this->calc->stat('hp', 95, 32, 'hp', 'atk'))->toBe(202)
        ->and($this->calc->stat('hp', 95, 32, 'atk', 'hp'))->toBe(202);
});

it('calcula el resto de stats como (base + 20 + SP) x alignment', function () {
    expect($this->calc->stat('atk', 125, 32))->toBe(177)
        ->and($this->calc->stat('atk', 125, 32, 'atk', 'spa'))->toBe(194)
        ->and($this->calc->stat('atk', 125, 0, 'spe', 'atk'))->toBe(130);
});

it('trunca hacia abajo al aplicar el alignment', function () {
    expect($this->calc->stat('spe', 85, 0, 'spe', 'atk'))->toBe(115);
});

it('trata como neutro un alignment sin plus o sin minus', function () {
    expect($this->calc->stat('atk', 125, 0, 'atk', null))->toBe(145)
        ->and($this->calc->stat('atk', 125, 0, null, 'atk'))->toBe(145)
        ->and($this->calc->stat('atk', 125, 0, 'atk', 'atk'))->toBe(145);
});

it('rechaza SP por encima del tope de 32', function () {
    expect(fn () => $this->calc->stat('atk', 100, 33))->toThrow(InvalidArgumentException::class);
});

it('rechaza estadisticas desconocidas', function () {
    expect(fn () => $this->calc->stat('luck', 100, 0))->toThrow(InvalidArgumentException::class);
});

it('calcula un spread completo', function () {
    $stats = $this->calc->spread(
        ['hp' => 100, 'atk' => 125, 'def' => 90, 'spa' => 60, 'spd' => 70, 'spe' => 85],
        ['hp' => 2, 'atk' => 32, 'def' => 0, 'spa' => 0, 'spd' => 0, 'spe' => 32],
        'atk',
        'spa',
    );

    expect($stats)->toBe([
        'hp' => 177,
        'atk' => 194,
        'def' => 110,
        'spa' => 72,
        'spd' => 90,
        'spe' => 137,
    ]);
});

it('convierte SP a EVs con la regla 4 + 8 por punto adicional', function (int $sp, int $evs) {
    expect($this->calc->spToEvs($sp))->toBe($evs);
})->with([
    [0, 0],
    [1, 4],
    [2, 12],
    [3, 20],
    [32, 252],
]);

it('convierte EVs a SP', function (int $evs, int $sp) {
    expect($this->calc->evsToSp($evs))->toBe($sp);
})->with([
    [0, 0],
    [3, 0],
    [4, 1],
    [11, 1],
    [12, 2],
    [252, 32],
    [255, 32],
]);

it('importa el spread clasico 4/252/252 como 65 SP y deja 1 punto libre', function () {
    $sp = [
        'hp' => $this->calc->evsToSp(4),
        'atk' => $this->calc->evsToSp(252),
        'def' => 0,
        'spa' => 0,
        'spd' => 0,
        'spe' => $this->calc->evsToSp(252),
    ];

    expect($sp)->toBe(['hp' => 1, 'atk' => 32, 'def' => 0, 'spa' => 0, 'spd' => 0, 'spe' => 32])
        ->and(array_sum($sp))->toBe(65)
        ->and(StatCalculator::SP_TOTAL - array_sum($sp))->toBe(1);
});

it('usa aritmetica entera y no coma flotante en el alignment', function () {
    expect($this->calc->modifier('atk', 'atk', 'spa'))->toBe(11)
        ->and($this->calc->modifier('spa', 'atk', 'spa'))->toBe(9)
        ->and($this->calc->modifier('def', 'atk', 'spa'))->toBe(10);

    foreach (range(1, 255) as $base) {
        $plus = $this->calc->stat('atk', $base, 0, 'atk', 'spa');
        $minus = $this->calc->stat('atk', $base, 0, 'spa', 'atk');
        expect($plus)->toBe(intdiv(($base + 20) * 11, 10))
            ->and($minus)->toBe(intdiv(($base + 20) * 9, 10));
    }
});
