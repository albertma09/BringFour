<?php

use App\Domain\Build\MetaRoster;
use App\Domain\Build\SlotDefaults;
use App\Domain\Meta\SpeciesLookup;
use App\Domain\Stats\SpreadAdvisor;
use Illuminate\Support\Facades\DB;

function porDefecto(string $slug): array
{
    $formatId = (int) DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->value('id');
    $regulationId = (int) DB::table('formats')->where('id', $formatId)->value('regulation_id');

    return app(SlotDefaults::class)->forSpecies(
        app(SpeciesLookup::class)->find($slug),
        $formatId,
        0,
        $regulationId,
        app(MetaRoster::class)->brought($formatId, 0, 1),
    );
}

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega'), replayFixture('turnos')]);
});

it('rellena los cuatro huecos aunque solo se hayan visto unos pocos movimientos', function () {
    $base = porDefecto('sneasler');

    expect($base['hueco']['movimientos'])->toHaveCount(4)
        ->and($base['fuente']['vistos'])->toBeGreaterThan(0)
        ->and($base['fuente']['vistos'] + $base['fuente']['deducidos'])->toBe(4);
});

it('no aplica el minimo de muestra: rellena con lo poco que haya', function () {
    $base = porDefecto('rillaboom');

    expect($base['fuente']['traidas'])->toBeLessThan(30)
        ->and($base['fuente']['traidas'])->toBeGreaterThan(0)
        ->and($base['fuente']['vistos'])->toBeGreaterThan(0);
});

it('no repite movimientos al completar con los deducidos', function () {
    $movimientos = porDefecto('clawitzer')['hueco']['movimientos'];

    expect($movimientos)->toBe(array_values(array_unique($movimientos)));
});

it('la habilidad devuelta siempre es una de la especie', function () {
    foreach (['rillaboom', 'sneasler', 'clawitzer', 'basculegion'] as $slug) {
        $base = porDefecto($slug);
        $especie = app(SpeciesLookup::class)->find($slug);
        $suyas = array_values(json_decode((string) $especie->abilities, true) ?: []);

        expect($suyas)->toContain($base['hueco']['habilidad']);
    }
});

it('descarta la habilidad ruidosa que casi nadie lleva', function () {
    $especie = app(SpeciesLookup::class)->find('sneasler');
    $base = porDefecto('sneasler');
    $cuota = $base['fuente']['habilidad']['cuota'];

    expect($cuota === null || $cuota >= 15.0)->toBeTrue()
        ->and($base['hueco']['habilidad'])->not->toBeNull()
        ->and($especie)->not->toBeNull();
});

it('el alineamiento existe y su signo concuerda con el reparto', function () {
    foreach (['rillaboom', 'sneasler', 'clawitzer', 'basculegion'] as $slug) {
        $base = porDefecto($slug);
        $fila = DB::table('alignments')->where('slug', $base['hueco']['alineamiento'])->first();

        expect($fila)->not->toBeNull()
            ->and($base['hueco']['sp'][$fila->plus_stat])->toBeGreaterThan(0)
            ->and($base['hueco']['sp'][$fila->minus_stat])->toBe(0);
    }
});

it('nunca reparte mas de lo que permite el juego', function () {
    foreach (['rillaboom', 'sneasler', 'clawitzer', 'basculegion', 'milotic'] as $slug) {
        $sp = porDefecto($slug)['hueco']['sp'];

        expect(array_sum($sp))->toBeLessThanOrEqual(SpreadAdvisor::TOTAL);

        foreach ($sp as $puntos) {
            expect($puntos)->toBeLessThanOrEqual(SpreadAdvisor::TOPE)
                ->and($puntos)->toBeGreaterThanOrEqual(0);
        }
    }
});

it('un pokemon que nadie ha traido sale entero deducido', function () {
    $slug = DB::table('species')
        ->whereNotIn('id', DB::table('replay_teams')->select('species_id'))
        ->whereNotNull('abilities')
        ->orderBy('id')
        ->value('slug');

    $base = porDefecto($slug);

    expect($base['fuente']['traidas'])->toBe(0)
        ->and($base['fuente']['vistos'])->toBe(0)
        ->and($base['hueco']['objeto'])->toBeNull()
        ->and($base['hueco']['habilidad'])->not->toBeNull();
});

it('el endpoint devuelve el hueco listo para editar', function () {
    $this->getJson('/api/builder/species/rillaboom/defaults')
        ->assertOk()
        ->assertJsonStructure([
            'hueco' => ['slug', 'movimientos', 'habilidad', 'objeto', 'sp', 'alineamiento'],
            'fuente' => ['traidas', 'vistos', 'deducidos', 'movimientos', 'habilidad', 'objeto', 'sp'],
        ])
        ->assertJsonPath('hueco.slug', 'rillaboom');
});
