<?php

use App\Domain\Meta\TeamQuery;
use Illuminate\Support\Facades\DB;

function formatoRegmc(): int
{
    return (int) DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->value('id');
}

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('agrupa las parejas que aparecen en el mismo Team Preview', function () {
    $cores = app(TeamQuery::class)->cores(formatoRegmc(), 0, 2, 1, 200);

    $parejas = array_map(
        fn (array $core) => implode('+', array_map(fn ($m) => $m['slug'], $core['miembros'])),
        $cores,
    );

    expect($parejas)->toContain('basculegion+sneasler')
        ->and($cores[0]['miembros'])->toHaveCount(2)
        ->and($cores[0]['n'])->toBeGreaterThanOrEqual(1);
});

it('no repite una especie dentro del mismo nucleo', function () {
    foreach (app(TeamQuery::class)->cores(formatoRegmc(), 0, 3, 1, 50) as $core) {
        $slugs = array_map(fn ($m) => $m['slug'], $core['miembros']);

        expect($slugs)->toHaveCount(3)
            ->and(array_unique($slugs))->toHaveCount(3);
    }
});

it('devuelve los nucleos ordenados de mas a menos repetidos', function () {
    $cores = app(TeamQuery::class)->cores(formatoRegmc(), 0, 2, 1, 20);
    $enes = array_map(fn (array $core) => $core['n'], $cores);

    expect($enes)->toBe(collect($enes)->sortDesc()->values()->all());
});

it('agrupa los equipos de seis identicos', function () {
    $equipos = app(TeamQuery::class)->teams(formatoRegmc(), 0, 1, 20);

    expect($equipos)->not->toBeEmpty()
        ->and($equipos[0]['miembros'])->toHaveCount(6)
        ->and($equipos[0]['id'])->toContain('-');
});

it('da el detalle de un equipo con lo que trae cada miembro', function () {
    $equipos = app(TeamQuery::class)->teams(formatoRegmc(), 0, 1, 1);
    $slugs = array_map(fn ($m) => $m['slug'], $equipos[0]['miembros']);

    $detalle = app(TeamQuery::class)->team(formatoRegmc(), 0, $slugs);

    expect($detalle['miembros'])->toHaveCount(6)
        ->and($detalle['n'])->toBeGreaterThan(0)
        ->and($detalle['completos'])->toBeLessThanOrEqual($detalle['n']);

    foreach ($detalle['miembros'] as $miembro) {
        expect($miembro['bring_pct'])->toBeGreaterThanOrEqual(0)
            ->and($miembro['bring_pct'])->toBeLessThanOrEqual(100);
    }
});

it('no acepta un equipo que no tenga seis', function () {
    expect(app(TeamQuery::class)->team(formatoRegmc(), 0, ['rillaboom', 'sneasler']))->toBeNull();
});

it('la api de equipos exige la muestra minima aunque se pida menos', function () {
    $respuesta = $this->getJson('/api/teams?min=1')->assertOk()->json();

    expect($respuesta['muestra']['min_sample'])->toBe(30)
        ->and($respuesta['equipos'])->toBe([]);
});

it('la api de nucleos solo acepta tamanos 2, 3 y 4', function () {
    $this->getJson('/api/teams/cores?size=9')->assertOk()->assertJsonPath('tamano', 2);
    $this->getJson('/api/teams/cores?size=3')->assertOk()->assertJsonPath('tamano', 3);
});

it('da 404 en un equipo que no existe', function () {
    $this->getJson('/api/teams/rillaboom-sneasler-incineroar-pelipper-gholdengo-milotic')->assertNotFound();
});
