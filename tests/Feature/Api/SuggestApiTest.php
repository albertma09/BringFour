<?php

use App\Domain\Meta\PartnerQuery;
use App\Domain\Meta\SetQuery;
use Illuminate\Support\Facades\DB;

function formatoSugerencias(): int
{
    return (int) DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->value('id');
}

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('no propone como companero al que ya esta elegido', function () {
    $resultado = app(PartnerQuery::class)->forSlugs(formatoSugerencias(), 0, ['rillaboom'], 1, 20);
    $slugs = array_column($resultado['companeros'], 'slug');

    expect($resultado['n'])->toBeGreaterThan(0)
        ->and($slugs)->not->toContain('rillaboom')
        ->and($slugs)->not->toBeEmpty();
});

it('ordena por afinidad y no por veces que aparece', function () {
    $resultado = app(PartnerQuery::class)->forSlugs(formatoSugerencias(), 0, ['rillaboom'], 1, 20);
    $afinidades = array_column($resultado['companeros'], 'afinidad');

    expect($afinidades)->toBe(collect($afinidades)->sortDesc()->values()->all());
});

it('mide la afinidad contra lo que a ese companero le tocaria por su cuenta', function () {
    $resultado = app(PartnerQuery::class)->forSlugs(formatoSugerencias(), 0, ['rillaboom'], 1, 40);

    foreach ($resultado['companeros'] as $companero) {
        expect($companero['veces'])->toEqualWithDelta($companero['juntos_pct'] / $companero['general_pct'], 0.01)
            ->and($companero['n'])->toBeLessThanOrEqual($resultado['n']);
    }
});

it('solo devuelve companeros de equipos que llevan todo lo elegido', function () {
    $uno = app(PartnerQuery::class)->forSlugs(formatoSugerencias(), 0, ['rillaboom'], 1, 40);
    $dos = app(PartnerQuery::class)->forSlugs(formatoSugerencias(), 0, ['rillaboom', 'incineroar'], 1, 40);

    expect($dos['n'])->toBeLessThanOrEqual($uno['n']);
});

it('da el conjunto visto con su muestra', function () {
    $speciesId = (int) DB::table('species')->where('slug', 'rillaboom')->value('id');
    $conjunto = app(SetQuery::class)->forSpecies($speciesId, formatoSugerencias(), 0, 1, 10);

    expect($conjunto['traidas'])->toBeGreaterThan(0);

    foreach ($conjunto['movimientos'] as $movimiento) {
        expect($movimiento['pct'])->toBeGreaterThan(0)
            ->and($movimiento['pct'])->toBeLessThanOrEqual(100)
            ->and($movimiento['n'])->toBeLessThanOrEqual($movimiento['base']);
    }
});

it('no atribuye a una especie la habilidad de otra', function () {
    $speciesId = (int) DB::table('species')->where('slug', 'rillaboom')->value('id');
    $conjunto = app(SetQuery::class)->forSpecies($speciesId, formatoSugerencias(), 0, 1, 10);
    $legales = json_decode((string) DB::table('species')->where('id', $speciesId)->value('abilities'), true);

    foreach ($conjunto['habilidades'] as $habilidad) {
        expect($legales)->toContain($habilidad['slug']);
    }
});

it('la api de companeros exige la muestra minima aunque se pida menos', function () {
    $respuesta = $this->getJson('/api/builder/partners?species=rillaboom&min=1')->assertOk()->json();

    expect($respuesta['muestra']['min_sample'])->toBe(30)
        ->and($respuesta['companeros'])->toBe([]);
});

it('la api de companeros da 404 con una especie inventada', function () {
    $this->getJson('/api/builder/partners?species=mewtwodos')->assertNotFound();
});

it('la api de conjunto exige la muestra minima', function () {
    $respuesta = $this->getJson('/api/builder/species/rillaboom/set?min=1')->assertOk()->json();

    expect($respuesta['muestra']['min_sample'])->toBe(30)
        ->and($respuesta['movimientos'])->toBe([])
        ->and($respuesta['habilidades'])->toBe([]);
});

it('el reparto sugerido cabe en los 66 puntos', function () {
    $respuesta = $this->getJson('/api/builder/species/rillaboom/spread?alignment=adamant')->assertOk()->json();

    expect($respuesta['total'])->toBe(66)
        ->and(max($respuesta['sp']))->toBeLessThanOrEqual(32)
        ->and($respuesta['alineamiento'])->toBe('adamant');
});

it('el reparto funciona sin alineamiento elegido', function () {
    $respuesta = $this->getJson('/api/builder/species/rillaboom/spread')->assertOk()->json();

    expect($respuesta['total'])->toBe(66)
        ->and($respuesta['alineamiento'])->toBeNull();
});

it('el reparto da 404 en una especie inventada', function () {
    $this->getJson('/api/builder/species/mewtwodos/spread')->assertNotFound();
});
