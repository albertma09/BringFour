<?php

use App\Domain\Replays\ParsedReplay;
use App\Domain\Replays\ParsedTurn;

function turnoDe(ParsedReplay $parsed, int $numero): ParsedTurn
{
    foreach ($parsed->turns as $turn) {
        if ($turn->number === $numero) {
            return $turn;
        }
    }

    throw new RuntimeException("El replay no tiene turno {$numero}");
}

function accionDe(ParsedTurn $turn, string $slot)
{
    return $turn->lastFor($slot);
}

it('resuelve la especie real aunque la etiqueta sea la forma base', function () {
    $accion = accionDe(turnoDe(parseFixture('turnos'), 3), 'p1b');

    expect($accion->actorSlug)->toBe('samurotthisui')
        ->and($accion->moveSlug)->toBe('sacredsword');
});

it('resuelve la especie real aunque el jugador le haya puesto mote', function () {
    $accion = accionDe(turnoDe(parseFixture('con-mega'), 3), 'p2b');

    expect($accion->actorSlug)->toBe('slowking')
        ->and($accion->moveSlug)->toBe('trickroom');
});

it('registra el movimiento con su objetivo', function () {
    $turno = turnoDe(parseFixture('con-mega'), 1);
    $accion = accionDe($turno, 'p1a');

    expect($accion->type)->toBe('move')
        ->and($accion->actorSlug)->toBe('rillaboom')
        ->and($accion->moveSlug)->toBe('fakeout')
        ->and($accion->targetSide)->toBe('p2')
        ->and($accion->targetSlot)->toBe('p2b')
        ->and($accion->forced)->toBeFalse();
});

it('distingue el cambio elegido del relevo obligado', function () {
    $turno = turnoDe(parseFixture('turnos'), 2);

    $elegido = $turno->actions[0];
    $relevo = accionDe($turno, 'p1a');

    expect($elegido->type)->toBe('switch')
        ->and($elegido->switchInSlug)->toBe('samurotthisui')
        ->and($elegido->forced)->toBeFalse()
        ->and($relevo->type)->toBe('switch')
        ->and($relevo->switchInSlug)->toBe('tinkaton')
        ->and($relevo->forced)->toBeTrue();
});

it('anota el motivo cuando el pokemon no pudo actuar', function () {
    $accion = accionDe(turnoDe(parseFixture('con-mega'), 1), 'p2b');

    expect($accion->type)->toBe('cant')
        ->and($accion->reason)->toBe('flinch')
        ->and($accion->isDecision())->toBeFalse();
});

it('no apunta un cant a quien ya habia decidido ese turno', function () {
    $turno = turnoDe(parseFixture('normal'), 4);
    $tipos = array_map(fn ($a) => $a->type, array_filter($turno->actions, fn ($a) => $a->slot === 'p1b'));

    expect($tipos)->toBe(['switch']);
});

it('mide los segundos que tardaron en decidir el turno', function () {
    expect(turnoDe(parseFixture('normal'), 4)->decisionSeconds)->toBe(33);
});

it('descuenta el tiempo del relevo obligado del turno siguiente', function () {
    expect(turnoDe(parseFixture('con-mega'), 3)->decisionSeconds)->toBe(25);
});

it('guarda la vida que habia al empezar el turno, no la de despues', function () {
    $parsed = parseFixture('normal');

    expect(accionDe(turnoDe($parsed, 3), 'p1b')->actorHpPct)->toBe(55)
        ->and(turnoDe($parsed, 4)->fieldState['activos']['p1b']['hp'])->toBe(38);
});

it('el terreno aparece en el turno siguiente al que se puso', function () {
    $parsed = parseFixture('normal');

    expect(turnoDe($parsed, 3)->fieldState['terreno'])->toBeNull()
        ->and(turnoDe($parsed, 4)->fieldState['terreno'])->toBe('grassyterrain');
});

it('aplica la megaevolucion al turno en que se declara', function () {
    $turno = turnoDe(parseFixture('turnos'), 1);

    expect($turno->fieldState['activos']['p1a']['especie'])->toBe('raichumegay')
        ->and(accionDe($turno, 'p1a')->actorSlug)->toBe('raichumegay');
});

it('no guarda turnos vacios al final de la partida', function () {
    $parsed = parseFixture('con-mega');

    expect($parsed->turnCount)->toBe(6)
        ->and($parsed->turns)->toHaveCount(5);
});
