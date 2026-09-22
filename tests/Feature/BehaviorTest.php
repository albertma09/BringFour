<?php

use App\Console\Commands\BehaviorBuild;
use Illuminate\Support\Facades\DB;

function construirPriors(array $fixtures = ['normal', 'con-mega', 'turnos']): void
{
    importFixtures(array_map('replayFixture', $fixtures));
    test()->artisan('behavior:build', ['--min' => 1])->assertSuccessful();
}

function prior(string $actor, string $rival, string $fase): array
{
    return DB::table('behavior_priors')
        ->where('prior_type', BehaviorBuild::PRIOR_TYPE)
        ->whereRaw("context->>'actor' = ?", [$actor])
        ->whereRaw("context->>'rival' = ?", [$rival])
        ->whereRaw("context->>'fase' = ?", [$fase])
        ->pluck('n', 'action_key')
        ->all();
}

it('cuenta la decision contra cada rival que habia en el campo', function () {
    construirPriors();

    expect(prior('rillaboom', 'grapploct', 'apertura'))->toBe(['move:fakeout' => 1])
        ->and(prior('rillaboom', 'hatterene', 'apertura'))->toBe(['move:fakeout' => 1]);
});

it('separa el turno 1 del resto de la partida', function () {
    construirPriors();

    expect(prior('raichumegay', 'ceruledge', 'apertura'))->toBe(['move:zapcannon' => 1])
        ->and(prior('raichumegay', 'ceruledge', 'medio'))->toBe([]);
});

it('no cuenta el relevo obligado como una decision', function () {
    construirPriors();

    $elegido = prior('tinkaton', 'ceruledge', 'medio');
    $obligado = prior('raichumegay', 'lucariomegaz', 'medio');

    expect($elegido['switch'] ?? 0)->toBe(1)
        ->and($obligado)->toBe([]);
});

it('no cuenta como decision el turno en que no se pudo actuar', function () {
    construirPriors();

    $filas = DB::table('behavior_priors')->whereRaw("context->>'actor' = ?", ['hatterene'])->count();

    expect($filas)->toBe(0);
});

it('los porcentajes de un contexto suman cien', function () {
    construirPriors();

    $sumas = DB::table('behavior_priors')
        ->selectRaw('context_hash, elo_bucket, sum(pct) total')
        ->groupBy('context_hash', 'elo_bucket')
        ->pluck('total');

    expect($sumas)->not->toBeEmpty();

    foreach ($sumas as $total) {
        expect(round((float) $total))->toBe(100.0);
    }
});

it('descarta los contextos que no llegan a la muestra minima', function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);

    $this->artisan('behavior:build', ['--min' => 30])->assertSuccessful();

    expect(DB::table('behavior_priors')->count())->toBe(0);
});

it('ensena la distribucion de decisiones de un contexto', function () {
    construirPriors();

    $this->artisan('meta:behavior', ['--actor' => 'Rillaboom', '--vs' => 'Grapploct', '--fase' => 'apertura'])
        ->expectsOutputToContain('Fake Out')
        ->expectsOutputToContain('1 decisiones observadas')
        ->assertSuccessful();
});

it('ofrece los rivales disponibles cuando no se indica ninguno', function () {
    construirPriors();

    $this->artisan('meta:behavior', ['--actor' => 'Rillaboom'])
        ->expectsOutputToContain('Rivales con muestra suficiente')
        ->assertSuccessful();
});

it('avisa en vez de inventar cuando no hay contexto', function () {
    construirPriors();

    $this->artisan('meta:behavior', ['--actor' => 'Rillaboom', '--vs' => 'Pikachu'])
        ->expectsOutputToContain('No hay muestra suficiente')
        ->assertSuccessful();
});

it('rechaza una fase que no existe', function () {
    $this->artisan('meta:behavior', ['--actor' => 'Rillaboom', '--fase' => 'final'])->assertFailed();
});
