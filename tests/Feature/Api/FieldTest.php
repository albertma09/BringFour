<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('a Rillaboom se le propone Hierba Parasita', function () {
    $conjunto = $this->getJson('/api/build/species/rillaboom/analysis')->assertOk()->json('conjunto');
    $slugs = array_column($conjunto['movimientos'], 'slug');

    expect($conjunto['campo']['campo'])->toBe('grassyterrain')
        ->and($slugs)->toContain('grassyglide');

    $glide = collect($conjunto['movimientos'])->firstWhere('slug', 'grassyglide');

    expect($glide['prioridad_campo'])->toBe(1)
        ->and($glide['motivo'])->toBe('prioridad');
});

it('calcula bajo el campo que pone su propia habilidad', function (string $slug, string $campo) {
    expect($this->getJson("/api/build/species/{$slug}/analysis")->assertOk()->json('conjunto.campo.campo'))->toBe($campo);
})->with([
    ['rillaboom', 'grassyterrain'],
    ['pelipper', 'raindance'],
    ['torkoal', 'sunnyday'],
    ['indeedeef', 'psychicterrain'],
]);

it('un pokemon sin habilidad de campo no inventa ninguno', function () {
    expect($this->getJson('/api/build/species/gholdengo/analysis')->assertOk()->json('conjunto.campo'))->toBeNull();
});

it('marca que velocidad necesita clima aunque no lo ponga el', function () {
    $velocidad = $this->getJson('/api/build/species/excadrill/analysis')->assertOk()->json('velocidad');

    expect($velocidad['necesita_clima'])->toBe('sandstorm')
        ->and($velocidad['doblada'])->toBeNull()
        ->and($velocidad['efectiva'])->toBe($velocidad['base']);
});

it('detecta quien pone el campo en el equipo y quien lo aprovecha', function () {
    $campo = $this->getJson('/api/build/team?equipo=tyranitar,excadrill,rillaboom')->assertOk()->json('campo');

    expect($campo['clima'])->toBe('sandstorm')
        ->and($campo['clima_quien'])->toBe('Tyranitar')
        ->and($campo['terreno'])->toBe('grassyterrain')
        ->and(array_column($campo['aprovechan'], 'quien'))->toContain('Excadrill');
});

it('avisa de quien necesita un clima que nadie pone', function () {
    $campo = $this->getJson('/api/build/team?equipo=excadrill,rillaboom,incineroar')->assertOk()->json('campo');

    expect($campo['clima'])->toBeNull()
        ->and(array_column($campo['huerfanos'], 'quien'))->toContain('Excadrill');
});

it('avisa cuando dos ponen climas distintos', function () {
    $campo = $this->getJson('/api/build/team?equipo=pelipper,torkoal,rillaboom')->assertOk()->json('campo');

    expect($campo['choques'])->not->toBeEmpty()
        ->and($campo['choques'][0]['tipo'])->toBe('clima');
});

it('avisa de que el Campo Psiquico bloquea prioridad', function () {
    $campo = $this->getJson('/api/build/team?equipo=indeedeef,rillaboom,incineroar')->assertOk()->json('campo');

    expect($campo['terreno'])->toBe('psychicterrain')
        ->and($campo['bloquea_prioridad'])->toBeTrue();
});

it('mide con que frecuencia aparece cada campo', function () {
    $vistos = $this->getJson('/api/build/team?equipo=rillaboom')->assertOk()->json('campos_vistos');

    expect($vistos['partidas'])->toBeGreaterThan(0)
        ->and($vistos['campos'])->not->toBeEmpty();

    foreach ($vistos['campos'] as $campo) {
        expect($campo['pct'])->toBeGreaterThan(0)
            ->and($campo['pct'])->toBeLessThanOrEqual(100)
            ->and($campo['clase'])->toBeIn(['clima', 'terreno']);
    }
});

it('propone companeros que aprovechan el campo del equipo', function () {
    $cubos = $this->getJson('/api/build/team/partners?equipo=pelipper,rillaboom&min=1')->assertOk()->json('cubos');
    $claves = [];

    foreach ($cubos as $lista) {
        foreach ($lista as $companero) {
            $claves = [...$claves, ...array_column($companero['razones'], 'clave')];
        }
    }

    expect($cubos)->toBeArray();

    if ($cubos !== []) {
        expect($claves)->not->toBeEmpty();
    }
});
