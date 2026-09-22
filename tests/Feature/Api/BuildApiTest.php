<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('analiza un pokemon del que no hay ni una partida', function () {
    $traido = DB::table('replay_teams as t')
        ->join('species as s', 's.id', '=', 't.species_id')
        ->where('s.slug', 'clawitzer')
        ->count();

    expect($traido)->toBe(0);

    $respuesta = $this->getJson('/api/build/species/clawitzer/analysis')->assertOk()->json();

    expect($respuesta['deducido'])->toBeTrue()
        ->and($respuesta['conjunto']['movimientos'])->not->toBeEmpty()
        ->and($respuesta['velocidad']['base'])->toBe(59);
});

it('propone solo movimientos especiales a un atacante especial', function () {
    $respuesta = $this->getJson('/api/build/species/clawitzer/analysis')->assertOk()->json();

    expect($respuesta['conjunto']['categoria'])->toBe('Special');

    foreach ($respuesta['conjunto']['movimientos'] as $movimiento) {
        expect($movimiento['category'])->not->toBe('Physical');
    }
});

it('no propone mas de cuatro movimientos ni los repite', function (string $slug) {
    $movimientos = $this->getJson("/api/build/species/{$slug}/analysis")->assertOk()->json('conjunto.movimientos');
    $slugs = array_column($movimientos, 'slug');

    expect($slugs)->toHaveCount(count(array_unique($slugs)))
        ->and(count($slugs))->toBeLessThanOrEqual(4);
})->with(['clawitzer', 'rillaboom', 'incineroar', 'gholdengo', 'pelipper']);

it('solo propone movimientos que la especie aprende en la regulacion', function () {
    $movimientos = $this->getJson('/api/build/species/clawitzer/analysis')->assertOk()->json('conjunto.movimientos');

    $legales = DB::table('learnsets as l')
        ->join('moves as m', 'm.id', '=', 'l.move_id')
        ->join('species as s', 's.id', '=', 'l.species_id')
        ->join('regulations as r', 'r.id', '=', 'l.regulation_id')
        ->where('s.slug', 'clawitzer')
        ->where('r.code', 'M-C')
        ->pluck('m.slug')
        ->all();

    foreach ($movimientos as $movimiento) {
        expect($legales)->toContain($movimiento['slug']);
    }
});

it('nunca mete en el conjunto un movimiento que golpea al companero', function (string $slug) {
    foreach ($this->getJson("/api/build/species/{$slug}/analysis")->assertOk()->json('conjunto.movimientos') as $movimiento) {
        expect($movimiento['golpea_aliado'] ?? false)->toBeFalse();
    }
})->with(['clawitzer', 'pelipper', 'gholdengo']);

it('separa las amenazas vistas de las que solo son posibles', function () {
    $respuesta = $this->getJson('/api/build/species/clawitzer/threats')->assertOk()->json();

    expect($respuesta['debilidades'])->toHaveKeys(['electric', 'grass'])
        ->and($respuesta['resistencias'])->toHaveKey('fire');

    foreach ($respuesta['confirmadas'] as $amenaza) {
        expect($amenaza['n'])->toBeGreaterThan(0);
    }

    foreach ($respuesta['posibles'] as $amenaza) {
        expect($amenaza['n'])->toBeNull();
    }
});

it('no se propone a si mismo como amenaza', function () {
    $amenazas = $this->getJson('/api/build/species/rillaboom/threats')->assertOk()->json();

    expect(array_column($amenazas['confirmadas'], 'slug'))->not->toContain('rillaboom');
});

it('exige la muestra minima tambien para los companeros', function () {
    expect($this->getJson('/api/build/species/clawitzer/structural-partners')->assertOk()->json('cubos'))->toBe([]);
});

it('da 404 en una especie inventada', function (string $ruta) {
    $this->getJson("/api/build/species/mewtwodos/{$ruta}")->assertNotFound();
})->with(['analysis', 'threats', 'structural-partners']);
