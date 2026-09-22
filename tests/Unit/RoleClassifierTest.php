<?php

use App\Domain\Build\RoleClassifier;

function stats(int $atk, int $spa, int $spe = 80): array
{
    return ['hp' => 80, 'atk' => $atk, 'def' => 80, 'spa' => $spa, 'spd' => 80, 'spe' => $spe];
}

it('separa el eje ofensivo por las estadisticas base', function (array $base, string $esperado) {
    expect((new RoleClassifier())->clasificar($base, [], [])['eje'])->toBe($esperado);
})->with([
    [stats(73, 120), 'especial'],
    [stats(125, 60), 'fisico'],
    [stats(110, 110), 'mixto'],
    [stats(60, 70), 'apoyo'],
    [stats(100, 95), 'mixto'],
    [stats(130, 60), 'fisico'],
]);

it('etiqueta el control de velocidad desde el repertorio', function () {
    $sin = (new RoleClassifier())->clasificar(stats(60, 70), [], ['protect']);
    $con = (new RoleClassifier())->clasificar(stats(60, 70), [], ['trickroom']);

    expect($sin['etiquetas'])->not->toContain('velocidad')
        ->and($con['etiquetas'])->toContain('velocidad')
        ->and($con['cubos'])->toContain('velocidad');
});

it('etiqueta clima y terreno desde la habilidad', function () {
    $clasificador = new RoleClassifier();

    expect($clasificador->clasificar(stats(60, 70), ['drizzle'], [])['etiquetas'])->toContain('clima')
        ->and($clasificador->clasificar(stats(60, 70), ['grassysurge'], [])['etiquetas'])->toContain('terreno')
        ->and($clasificador->clasificar(stats(60, 70), ['intimidate'], [])['etiquetas'])->toBeEmpty();
});

it('solo marca remate si sube y ademas pega', function () {
    $clasificador = new RoleClassifier();

    expect($clasificador->clasificar(stats(130, 60), [], ['swordsdance'])['etiquetas'])->toContain('remate')
        ->and($clasificador->clasificar(stats(60, 60), [], ['swordsdance'])['etiquetas'])->not->toContain('remate')
        ->and($clasificador->clasificar(stats(130, 60), [], ['protect'])['etiquetas'])->not->toContain('remate');
});

it('un mixto entra en los dos cubos de ataque', function () {
    expect((new RoleClassifier())->clasificar(stats(110, 110), [], [])['cubos'])
        ->toContain('fisico')
        ->toContain('especial');
});

it('no devuelve cubos inventados', function () {
    foreach ((new RoleClassifier())->clasificar(stats(130, 60), ['drizzle'], ['trickroom', 'swordsdance'])['cubos'] as $cubo) {
        expect(RoleClassifier::CUBOS)->toContain($cubo);
    }
});
