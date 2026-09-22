<?php

use App\Domain\Meta\ClosingQuery;
use Illuminate\Support\Facades\DB;

function formatoCierre(): int
{
    return (int) DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->value('id');
}

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('nunca cierra mas partidas de las que gana', function () {
    foreach (app(ClosingQuery::class)->rates(formatoCierre(), 0, 1) as $slug => $tasa) {
        expect($tasa['cierra'])->toBeLessThanOrEqual($tasa['ganadas'])
            ->and($tasa['pct'])->toBeGreaterThanOrEqual(0)
            ->and($tasa['pct'])->toBeLessThanOrEqual(100)
            ->and($tasa['intervalo'][0])->toBeLessThanOrEqual($tasa['pct'])
            ->and($tasa['intervalo'][1])->toBeGreaterThanOrEqual($tasa['pct']);
    }
});

it('una especie sin partidas ganadas no devuelve tasa', function () {
    expect(app(ClosingQuery::class)->forSpecies('clawitzer', formatoCierre(), 0, 1))->toBeNull();
});

it('respeta la muestra minima', function () {
    expect(app(ClosingQuery::class)->rates(formatoCierre(), 0, 999))->toBe([]);
});

it('cuenta la mega bajo su forma base', function () {
    $conMega = DB::table('replay_turns as t')
        ->join('replays as r', 'r.id', '=', 't.replay_id')
        ->whereRaw("t.field_state::text like '%mega%'")
        ->exists();

    expect($conMega)->toBeTrue();

    foreach (array_keys(app(ClosingQuery::class)->rates(formatoCierre(), 0, 1)) as $slug) {
        expect(DB::table('species')->where('slug', $slug)->value('is_mega'))->toBeFalsy();
    }
});

function cubosDe(array $excluir = []): array
{
    $formatId = formatoCierre();
    $regulationId = (int) DB::table('formats')->where('id', $formatId)->value('regulation_id');
    $especie = app(App\Domain\Meta\SpeciesLookup::class)->find('clawitzer');
    $meta = app(App\Domain\Build\MetaRoster::class)->brought($formatId, 0, 1);

    return app(App\Domain\Build\StructuralPartnerQuery::class)->forSpecies(
        $especie,
        $regulationId,
        $meta,
        ['ritmo' => 'lento'],
        [],
        app(ClosingQuery::class)->rates($formatId, 0, 1),
        $excluir,
        4,
    );
}

it('agrupa los companeros por papel y no repite dentro de un cubo', function () {
    $cubos = cubosDe();

    expect($cubos)->not->toBeEmpty();

    foreach ($cubos as $nombre => $lista) {
        $slugs = array_column($lista, 'slug');

        expect($slugs)->toHaveCount(count(array_unique($slugs)))
            ->and($slugs)->not->toContain('clawitzer')
            ->and($nombre)->toBeIn(App\Domain\Build\RoleClassifier::CUBOS)
            ->and(count($slugs))->toBeLessThanOrEqual(4);
    }
});

it('mete a cada uno en el cubo que le toca por sus estadisticas', function () {
    foreach (cubosDe() as $nombre => $lista) {
        foreach ($lista as $companero) {
            if ($nombre === 'fisico') {
                expect($companero['eje'])->toBeIn(['fisico', 'mixto']);
            }

            if ($nombre === 'especial') {
                expect($companero['eje'])->toBeIn(['especial', 'mixto']);
            }

            if ($nombre === 'remate') {
                expect($companero['etiquetas'])->toContain('remate');
            }
        }
    }
});

it('no propone a nadie que ya este en el equipo', function () {
    foreach (cubosDe(['rillaboom', 'incineroar']) as $lista) {
        foreach (array_column($lista, 'slug') as $slug) {
            expect($slug)->not->toBeIn(['rillaboom', 'incineroar', 'clawitzer']);
        }
    }
});

it('el analisis dice el papel del pokemon elegido', function () {
    $respuesta = $this->getJson('/api/build/species/clawitzer/analysis')->assertOk()->json();

    expect($respuesta['papel']['eje'])->toBe('especial')
        ->and($respuesta['cierre'])->toBeNull();
});
