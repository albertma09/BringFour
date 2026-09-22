<?php

it('sirve el esqueleto de la aplicacion en cualquier ruta de pantalla', function (string $ruta) {
    $this->withoutVite()
        ->get($ruta)
        ->assertOk()
        ->assertSee('id="app"', false);
})->with(['/', '/matchups', '/equipos', '/constructor', '/pokedex', '/pokedex/rillaboom', '/ruta-inventada']);

it('no se traga las rutas de la api', function () {
    $this->withoutVite()
        ->getJson('/api/formats')
        ->assertOk()
        ->assertJsonStructure(['formatos']);
});

it('no se traga la comprobacion de salud', function () {
    $this->withoutVite()->get('/up')->assertOk()->assertDontSee('id="app"', false);
});
