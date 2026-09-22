<?php

use App\Domain\Stats\SpreadAdvisor;

function bases(int $hp, int $atk, int $def, int $spa, int $spd, int $spe): array
{
    return ['hp' => $hp, 'atk' => $atk, 'def' => $def, 'spa' => $spa, 'spd' => $spd, 'spe' => $spe];
}

it('nunca reparte mas de 66 puntos ni mas de 32 en una estadistica', function (array $base, ?string $plus, ?string $minus) {
    $reparto = (new SpreadAdvisor())->suggest($base, $plus, $minus);

    expect(array_sum($reparto['sp']))->toBe(SpreadAdvisor::TOTAL)
        ->and(max($reparto['sp']))->toBeLessThanOrEqual(SpreadAdvisor::TOPE)
        ->and(min($reparto['sp']))->toBeGreaterThanOrEqual(0);
})->with([
    [bases(100, 125, 90, 60, 70, 85), 'atk', 'spa'],
    [bases(95, 115, 90, 80, 90, 60), null, null],
    [bases(60, 45, 70, 65, 80, 130), 'spe', 'atk'],
    [bases(70, 30, 50, 40, 60, 30), null, null],
    [bases(50, 50, 50, 50, 50, 50), 'spd', 'spe'],
]);

it('reparte a la ofensiva que el alineamiento sube aunque la base diga lo contrario', function () {
    $fisico = (new SpreadAdvisor())->suggest(bases(70, 60, 70, 130, 90, 100), 'atk', 'spa');

    expect($fisico['ofensiva'])->toBe('atk')
        ->and($fisico['sp']['atk'])->toBe(32)
        ->and($fisico['sp']['spa'])->toBe(0);
});

it('un alineamiento que baja velocidad convierte el reparto en aguante', function () {
    $base = bases(95, 115, 90, 80, 90, 60);

    $rapido = (new SpreadAdvisor())->suggest($base, 'atk', 'spa');
    $lento = (new SpreadAdvisor())->suggest($base, 'atk', 'spe');

    expect($lento['ritmo'])->toBe('lento')
        ->and($lento['sp']['spe'])->toBe(0)
        ->and($lento['sp']['hp'])->toBeGreaterThan($rapido['sp']['hp']);
});

it('un pokemon sin ofensiva se trata como apoyo', function () {
    $reparto = (new SpreadAdvisor())->suggest(bases(70, 30, 50, 40, 60, 30));

    expect($reparto['papel'])->toBe('apoyo')
        ->and($reparto['sp']['hp'])->toBe(32)
        ->and($reparto['sp']['atk'])->toBe(0);
});

it('invierte en la defensa mas floja', function () {
    $reparto = (new SpreadAdvisor())->suggest(bases(70, 40, 100, 40, 55, 30));

    expect($reparto['defensa'])->toBe('spd')
        ->and($reparto['sp']['spd'])->toBeGreaterThan($reparto['sp']['def']);
});

it('da una razon a cada punto que reparte', function () {
    $reparto = (new SpreadAdvisor())->suggest(bases(100, 125, 90, 60, 70, 85), 'atk', 'spa');

    expect(array_sum(array_column($reparto['razones'], 'sp')))->toBe(SpreadAdvisor::TOTAL);

    foreach ($reparto['razones'] as $razon) {
        expect($razon['clave'])->not->toBeEmpty()
            ->and($reparto['sp'][$razon['stat']])->toBeGreaterThan(0);
    }
});
