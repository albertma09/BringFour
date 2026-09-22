<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('devuelve los formatos procesables', function () {
    $this->getJson('/api/formats')
        ->assertOk()
        ->assertJsonPath('formatos.0.battle_type', 'doubles')
        ->assertJsonStructure(['formatos' => [['showdown_id', 'regulacion', 'bring_count']]]);
});

it('dice cuantas partidas hay en cada corte de elo', function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega')]);

    $formato = collect($this->getJson('/api/formats')->assertOk()->json()['formatos'])
        ->firstWhere('showdown_id', 'gen9championsvgc2026regmc');

    expect($formato['cortes'])->toHaveKeys(['0', '1500', '1630', '1760'])
        ->and($formato['cortes']['0'])->toBeGreaterThan(0);

    $anterior = null;

    foreach (['0', '1500', '1630', '1760'] as $corte) {
        if ($anterior !== null) {
            expect($formato['cortes'][$corte])->toBeLessThanOrEqual($anterior);
        }

        $anterior = $formato['cortes'][$corte];
    }
});

it('acompana todo porcentaje de su muestra', function () {
    $respuesta = $this->getJson('/api/meta/bring-rates?min=1')->assertOk()->json();

    expect($respuesta['muestra'])->toHaveKeys(['total', 'completos', 'descartados', 'min_sample', 'fuente']);

    foreach ($respuesta['especies'] as $especie) {
        expect($especie)->toHaveKeys(['n', 'bring_pct', 'lead_pct'])
            ->and($especie['n'])->toBeGreaterThan(0);
    }
});

it('no deja bajar la muestra minima por la url', function () {
    $respuesta = $this->getJson('/api/meta/bring-rates?min=1')->assertOk()->json();

    expect($respuesta['muestra']['min_sample'])->toBe(30)
        ->and($respuesta['especies'])->toBe([]);
});

it('ignora un corte de elo que no sea uno de los tramos', function () {
    $this->getJson('/api/meta/bring-rates?elo=1234')
        ->assertOk()
        ->assertJsonPath('muestra.elo_bucket', 0);
});

it('da 404 en un formato que no existe', function () {
    $this->getJson('/api/meta/bring-rates?format=gen9inventado')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('da 404 en una especie que no existe', function () {
    $this->getJson('/api/meta/species/mewtwodos/matchups')->assertNotFound();
});

it('devuelve la lista vacia y la muestra cuando no hay datos suficientes', function () {
    $this->getJson('/api/meta/species/rillaboom/matchups')
        ->assertOk()
        ->assertJsonPath('especies', [])
        ->assertJsonPath('muestra.min_sample', 30)
        ->assertJsonStructure(['muestra' => ['completos', 'enfrentados', 'ocultas']]);
});

it('ofrece los contextos de conducta cuando no se indica rival', function () {
    $this->artisan('behavior:build', ['--min' => 1])->assertSuccessful();

    $respuesta = $this->getJson('/api/meta/species/rillaboom/behavior')->assertOk()->json();

    expect($respuesta['contextos'])->not->toBeEmpty()
        ->and($respuesta['contextos'][0])->toHaveKeys(['rival', 'fase', 'n']);
});

it('devuelve la distribucion con su intervalo', function () {
    $this->artisan('behavior:build', ['--min' => 1])->assertSuccessful();

    $respuesta = $this->getJson('/api/meta/species/rillaboom/behavior?vs=grapploct&fase=apertura')
        ->assertOk()
        ->json();

    expect($respuesta['muestra']['n'])->toBe(1)
        ->and($respuesta['acciones'][0]['action_key'])->toBe('move:fakeout')
        ->and($respuesta['acciones'][0]['intervalo'])->toHaveCount(2);
});

it('rechaza una fase que no existe', function () {
    $this->getJson('/api/meta/species/rillaboom/behavior?vs=grapploct&fase=final')
        ->assertStatus(400);
});

it('no expone nunca el nombre del jugador', function () {
    $columnas = DB::getSchemaBuilder()->getColumnListing('replays');

    expect($columnas)->not->toContain('p1_name')
        ->and($columnas)->toContain('p1_hash');
});
