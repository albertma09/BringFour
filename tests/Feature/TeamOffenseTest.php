<?php

use App\Domain\Build\MetaRoster;
use App\Domain\Build\SetBuilder;
use App\Domain\Build\TeamOffense;
use App\Domain\Build\TypeChart;
use App\Domain\Meta\SpeciesLookup;
use Illuminate\Support\Facades\DB;

function contextoOfensiva(): array
{
    $formatId = (int) DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->value('id');

    return [
        $formatId,
        (int) DB::table('formats')->where('id', $formatId)->value('regulation_id'),
        app(MetaRoster::class)->brought($formatId, 0, 1),
    ];
}

function ofensiva(array $slugs, int $min = 1): array
{
    [$formatId, $regulationId, $meta] = contextoOfensiva();
    $lookup = app(SpeciesLookup::class);

    return app(TeamOffense::class)->analyse(
        array_map(fn (string $slug) => $lookup->find($slug), $slugs),
        $formatId,
        0,
        $regulationId,
        $meta,
        $min,
    );
}

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('un equipo monotipo llega a poca gente y repite golpe', function () {
    $r = ofensiva(['clawitzer', 'milotic', 'pelipper', 'basculegion']);

    expect($r['pct'])->toBeLessThan(100.0)
        ->and($r['intocables'])->not->toBeEmpty()
        ->and(array_column($r['repetidos'], 'tipo'))->toContain('water');
});

it('no marca como intocable a quien si recibe un golpe super eficaz', function () {
    $r = ofensiva(['clawitzer', 'milotic', 'pelipper', 'basculegion']);
    $chart = app(TypeChart::class);

    foreach ($r['intocables'] as $intocable) {
        foreach ($r['tipos'] as $tipo) {
            expect($chart->multiplier($tipo, $intocable['tipos']))->toBeLessThan(2.0);
        }
    }
});

it('las ganancias del tipo que falta van de mayor a menor y nunca son negativas', function () {
    $ganancias = array_column(ofensiva(['clawitzer', 'milotic'])['mejor_anadido'], 'ganancia');

    expect($ganancias)->toBe(collect($ganancias)->sortDesc()->values()->all());

    foreach ($ganancias as $ganancia) {
        expect($ganancia)->toBeGreaterThan(0.0);
    }
});

it('sin muestra suficiente deduce el conjunto en vez de inventarse lo visto', function () {
    $origenes = array_column(ofensiva(['clawitzer', 'milotic'], 100000)['fuentes'], 'origen');

    expect(array_unique($origenes))->toBe(['deducido']);
});

it('con muestra usa lo que se le ha visto lanzar', function () {
    $fuente = ofensiva(['sneasler'], 1)['fuentes'][0];

    expect($fuente['origen'])->toBe('visto')
        ->and($fuente['n'])->toBeGreaterThan(0)
        ->and($fuente['tipos'])->toContain('fighting');
});

it('descarta el golpe anecdotico dentro de lo visto', function () {
    expect(ofensiva(['sneasler'], 1)['fuentes'][0]['tipos'])->not->toContain('poison');
});

it('un tipo repetido que no es super eficaz contra nadie no se cuenta como redundante', function () {
    foreach (ofensiva(['clawitzer', 'milotic', 'pelipper', 'basculegion'])['repetidos'] as $repetido) {
        expect($repetido['tipo'])->not->toBe('normal');
    }
});

it('sin huecos del equipo el conjunto propuesto no cambia', function () {
    [, $regulationId, $meta] = contextoOfensiva();
    $especie = app(SpeciesLookup::class)->find('clawitzer');
    $builder = app(SetBuilder::class);

    $solo = $builder->build($especie, $regulationId, $meta);
    $vacio = $builder->build($especie, $regulationId, $meta, []);

    expect(array_column($vacio['movimientos'], 'slug'))->toBe(array_column($solo['movimientos'], 'slug'))
        ->and($solo['ajustado_al_equipo'])->toBeFalse();
});

it('marca el conjunto como ajustado cuando recibe huecos del equipo', function () {
    [, $regulationId, $meta] = contextoOfensiva();
    $huecos = [['slug' => 'garchomp', 'name' => 'Garchomp', 'tipos' => ['Dragon', 'Ground']]];

    $conjunto = app(SetBuilder::class)->build(
        app(SpeciesLookup::class)->find('clawitzer'),
        $regulationId,
        $meta,
        $huecos,
    );

    expect($conjunto['ajustado_al_equipo'])->toBeTrue()
        ->and($conjunto['movimientos'])->not->toBeEmpty();
});

it('el analisis de equipo expone la cobertura ofensiva por api', function () {
    $this->getJson('/api/build/team?equipo=clawitzer')
        ->assertOk()
        ->assertJsonStructure(['ofensiva' => ['pct', 'pobre', 'intocables', 'repetidos', 'mejor_anadido', 'fuentes']]);
});
