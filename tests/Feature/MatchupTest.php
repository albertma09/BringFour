<?php

beforeEach(function () {
    importFixtures([replayFixture('normal'), replayFixture('con-mega')]);
});

it('solo mira los lados cuyo rival enseno la especie', function () {
    $this->artisan('meta:matchup', ['--vs' => 'Baxcalibur', '--min' => 1])
        ->expectsOutputToContain('se enfrentaron a Baxcalibur: 1 de')
        ->expectsOutputToContain('Sneasler')
        ->doesntExpectOutputToContain('Farigiraf')
        ->assertSuccessful();
});

it('acepta el nombre con guion igual que el slug', function () {
    $this->artisan('meta:matchup', ['--vs' => 'Arcanine-Hisui', '--min' => 1])->assertSuccessful();
    $this->artisan('meta:matchup', ['--vs' => 'arcaninehisui', '--min' => 1])->assertSuccessful();
});

it('no dice nada de una especie que ningun rival enseno', function () {
    $this->artisan('meta:matchup', ['--vs' => 'Pikachu', '--min' => 1])
        ->expectsOutputToContain('se enfrentaron a Pikachu: 0 de')
        ->expectsOutputToContain('Ninguna especie llega a')
        ->assertSuccessful();
});

it('calla en vez de publicar porcentajes sin muestra', function () {
    $this->artisan('meta:matchup', ['--vs' => 'Baxcalibur'])
        ->expectsOutputToContain('Ninguna especie llega a 30 observaciones')
        ->assertSuccessful();
});

it('falla si la especie no existe', function () {
    $this->artisan('meta:matchup', ['--vs' => 'Mewtwodos'])->assertFailed();
    $this->artisan('meta:matchup', [])->assertFailed();
});
