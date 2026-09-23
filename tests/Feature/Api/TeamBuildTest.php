<?php

use App\Domain\Build\MetaRoster;
use App\Domain\Build\SwapAdvisor;
use App\Domain\Build\TeamAnalyzer;
use App\Domain\Build\TypeUsage;
use App\Domain\Meta\ClosingQuery;
use App\Domain\Meta\SpeciesLookup;
use Illuminate\Support\Facades\DB;

function formatoEquipo(): int
{
    return (int) DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->value('id');
}

function analizar(array $slugs): array
{
    $formatId = formatoEquipo();
    $regulationId = (int) DB::table('formats')->where('id', $formatId)->value('regulation_id');
    $lookup = app(SpeciesLookup::class);
    $meta = app(MetaRoster::class)->brought($formatId, 0, 1);
    $pesos = app(TypeUsage::class)->shares($formatId, 0);
    $cierres = app(ClosingQuery::class)->rates($formatId, 0, 1);

    $analisis = app(TeamAnalyzer::class)->analyse(
        array_map(fn (string $slug) => $lookup->find($slug), $slugs),
        $formatId,
        0,
        $regulationId,
        $meta,
        $pesos,
        $cierres,
    );

    return [$analisis, $meta, $pesos, $cierres, $regulationId];
}

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('detecta la debilidad compartida de un equipo monotipo', function () {
    [$analisis] = analizar(['clawitzer', 'milotic', 'pelipper', 'basculegion']);
    $tipos = array_column($analisis['compartidas'], 'tipo');

    expect($tipos)->toContain('electric')
        ->and($tipos)->toContain('grass');

    $electrico = collect($analisis['compartidas'])->firstWhere('tipo', 'electric');

    expect($electrico['cuantos'])->toBe(4)
        ->and($electrico['resisten'])->toBe(0);
});

it('ordena las carencias por lo que se lanza de verdad y no por la tabla de tipos', function () {
    [$analisis] = analizar(['clawitzer', 'milotic']);
    $pesos = array_column($analisis['sin_resistir'], 'peso');

    expect($pesos)->toBe(collect($pesos)->sortDesc()->values()->all());
});

it('con un solo pokemon no inventa debilidades compartidas', function () {
    [$analisis] = analizar(['clawitzer']);

    expect($analisis['compartidas'])->toBe([])
        ->and($analisis['miembros'])->toHaveCount(1)
        ->and($analisis['reparto']['sesgado'])->toBeNull();
});

it('una amenaza del equipo toca al menos a dos', function () {
    [$analisis] = analizar(['clawitzer', 'milotic', 'pelipper', 'basculegion']);

    foreach ($analisis['amenazas'] as $amenaza) {
        expect(count($amenaza['toca']))->toBeGreaterThanOrEqual(2)
            ->and($amenaza['n'])->toBeGreaterThan(0);
    }
});

it('propone sacar al que no aporta nada unico', function () {
    [$analisis, $meta, $pesos, $cierres, $regulationId] = analizar(['clawitzer', 'milotic', 'pelipper', 'basculegion']);
    $papeles = app(App\Domain\Build\RoleClassifier::class)->forMany(array_column($meta, 'slug'), $regulationId);

    $cambio = app(SwapAdvisor::class)->advise($analisis, $meta, $papeles, $cierres, $pesos, 4);

    expect($cambio['redundantes'])->not->toBeEmpty()
        ->and($cambio['propuesta'])->not->toBeNull()
        ->and($cambio['propuesta']['sale']['aporta']['valor'])->toBe(0.0)
        ->and($cambio['propuesta']['pareja'])->not->toBeNull();
});

it('el sustituto nunca reintroduce la debilidad que venia a arreglar', function () {
    [$analisis, $meta, $pesos, $cierres, $regulationId] = analizar(['clawitzer', 'milotic', 'pelipper', 'basculegion']);
    $papeles = app(App\Domain\Build\RoleClassifier::class)->forMany(array_column($meta, 'slug'), $regulationId);
    $chart = app(App\Domain\Build\TypeChart::class);

    $cambio = app(SwapAdvisor::class)->advise($analisis, $meta, $papeles, $cierres, $pesos, 6);
    $tipo = $cambio['propuesta']['arregla']['tipo'];

    expect($cambio['propuesta']['entran'])->not->toBeEmpty();

    foreach ($cambio['propuesta']['entran'] as $entra) {
        expect($chart->multiplier($tipo, $entra['tipos']))->toBeLessThan(1.0);
    }
});

it('no propone cambio con menos de tres pokemon', function () {
    [$analisis, $meta, $pesos, $cierres] = analizar(['clawitzer', 'milotic']);

    expect(app(SwapAdvisor::class)->advise($analisis, $meta, [], $cierres, $pesos, 4)['propuesta'])->toBeNull();
});

it('el que sale nunca es el propuesto para entrar', function () {
    [$analisis, $meta, $pesos, $cierres, $regulationId] = analizar(['clawitzer', 'milotic', 'pelipper', 'basculegion']);
    $papeles = app(App\Domain\Build\RoleClassifier::class)->forMany(array_column($meta, 'slug'), $regulationId);

    $propuesta = app(SwapAdvisor::class)->advise($analisis, $meta, $papeles, $cierres, $pesos, 6)['propuesta'];

    expect(array_column($propuesta['entran'], 'slug'))
        ->not->toContain($propuesta['sale']['slug'])
        ->not->toContain('milotic')
        ->not->toContain('pelipper');
});

it('la api pide equipo y no revienta sin el', function () {
    $this->getJson('/api/build/team')->assertStatus(400);
    $this->getJson('/api/build/team/partners')->assertStatus(400);
});

it('la api da 404 con una especie inventada en el equipo', function () {
    $this->getJson('/api/build/team?equipo=rillaboom,mewtwodos')->assertNotFound();
});

it('la api del equipo responde con el analisis completo', function () {
    $respuesta = $this->getJson('/api/build/team?equipo=clawitzer,milotic,pelipper')->assertOk()->json();

    expect($respuesta)->toHaveKeys(['miembros', 'papeles', 'reparto', 'velocidad', 'compartidas', 'sin_resistir', 'amenazas', 'cambio'])
        ->and($respuesta['miembros'])->toHaveCount(3);
});
