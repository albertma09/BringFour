<?php

it('lista solo especies construibles de la regulacion', function () {
    $respuesta = $this->getJson('/api/species')->assertOk()->json();

    expect($respuesta['total'])->toBe(382)
        ->and($respuesta['especies'][0])->toHaveKeys(['slug', 'name', 'types', 'base_stats', 'sprite']);
});

it('busca por nombre en ingles y en espanol', function () {
    $ingles = $this->getJson('/api/species?q=rillaboom')->assertOk()->json();
    $espanol = $this->getJson('/api/species?q=Mega-Absol')->assertOk()->json();

    expect($ingles['total'])->toBe(1)
        ->and($ingles['especies'][0]['slug'])->toBe('rillaboom')
        ->and($espanol['especies'][0]['slug'])->toBe('absolmega');
});

it('devuelve la ficha con habilidades y legalidad', function () {
    $respuesta = $this->getJson('/api/species/rillaboom')->assertOk()->json();

    expect($respuesta['especie']['legal'])->toBeTrue()
        ->and($respuesta['especie']['abilities'])->not->toBeEmpty()
        ->and($respuesta['especie']['types'])->toContain('Grass');
});

it('resuelve el sprite de una mega sin dibujo con su forma base y la megapiedra', function () {
    $respuesta = $this->getJson('/api/species/staraptormega')->assertOk()->json();

    expect($respuesta['especie']['sprite'])->toBe('staraptor.png')
        ->and($respuesta['especie']['sprite_stone'])->toBe('staraptite')
        ->and($respuesta['especie']['is_mega'])->toBeTrue();
});

it('usa su propio dibujo cuando la mega si lo tiene', function () {
    $respuesta = $this->getJson('/api/species/absolmega')->assertOk()->json();

    expect($respuesta['especie']['sprite'])->toBe('absolmega.png')
        ->and($respuesta['especie']['sprite_stone'])->toBeNull();
});

it('indica la megapiedra tanto desde la forma base como desde la mega', function () {
    $base = $this->getJson('/api/species/absol')->assertOk()->json();
    $mega = $this->getJson('/api/species/absolmega')->assertOk()->json();

    expect($base['especie']['megapiedra']['slug'])->toBe('absolite')
        ->and($mega['especie']['megapiedra']['slug'])->toBe('absolite');
});

it('da 404 en una especie inventada', function () {
    $this->getJson('/api/species/mewtwodos')->assertNotFound();
});
