<?php

it('devuelve los 25 alineamientos con sus subidas y bajadas', function () {
    $respuesta = $this->getJson('/api/builder/alignments')->assertOk()->json();

    expect($respuesta['alineamientos'])->toHaveCount(25)
        ->and(collect($respuesta['alineamientos'])->where('neutral', true))->toHaveCount(5);
});

it('solo ofrece movimientos que la especie puede aprender en la regulacion', function () {
    $respuesta = $this->getJson('/api/builder/species/rillaboom')->assertOk()->json();
    $slugs = collect($respuesta['movimientos'])->pluck('slug');

    expect($slugs)->toContain('grassyglide')
        ->and($slugs)->not->toContain('surf')
        ->and($respuesta['especie']['abilities'])->not->toBeEmpty();
});

it('marca la megapiedra que corresponde a la especie', function () {
    $respuesta = $this->getJson('/api/builder/species/absol')->assertOk()->json();
    $piedra = collect($respuesta['objetos'])->firstWhere('slug', 'absolite');

    expect($piedra['mega'])->toBe('absolmega');
});

it('no marca como mega una piedra de otra especie', function () {
    $respuesta = $this->getJson('/api/builder/species/rillaboom')->assertOk()->json();
    $piedra = collect($respuesta['objetos'])->firstWhere('slug', 'absolite');

    expect($piedra['mega'])->toBeNull();
});

it('da 404 en una especie inventada', function () {
    $this->getJson('/api/builder/species/mewtwodos')->assertNotFound();
});
