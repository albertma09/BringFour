<?php

use Illuminate\Support\Facades\DB;

function regulationId(string $code): int
{
    return DB::table('regulations')->where('code', $code)->value('id');
}

function isLegal(string $code, string $entityType, string $slug): bool
{
    $table = $entityType === 'species' ? 'species' : $entityType;

    return DB::table('legality')
        ->join($table, function ($join) use ($table) {
            $join->on('legality.entity_id', '=', "{$table}.id");
        })
        ->where('legality.regulation_id', regulationId($code))
        ->where('legality.entity_type', $entityType)
        ->where("{$table}.slug", $slug)
        ->exists();
}

function learns(string $code, string $species, string $move): bool
{
    return DB::table('learnsets')
        ->join('species', 'species.id', '=', 'learnsets.species_id')
        ->join('moves', 'moves.id', '=', 'learnsets.move_id')
        ->where('learnsets.regulation_id', regulationId($code))
        ->where('species.slug', $species)
        ->where('moves.slug', $move)
        ->exists();
}

it('tiene las regulaciones y formatos cargados', function () {
    expect(DB::table('regulations')->count())->toBeGreaterThanOrEqual(2)
        ->and(DB::table('regulations')->where('is_active', true)->count())->toBe(1)
        ->and(DB::table('regulations')->where('is_active', true)->value('code'))->toBe('M-C')
        ->and(DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->exists())->toBeTrue();
});

it('tiene exactamente 25 stat alignments, 5 de ellos neutros', function () {
    expect(DB::table('alignments')->count())->toBe(25)
        ->and(DB::table('alignments')->whereNull('plus_stat')->count())->toBe(5);
});

it('no tiene learnsets huerfanos', function () {
    $orphanSpecies = DB::table('learnsets')
        ->leftJoin('species', 'species.id', '=', 'learnsets.species_id')
        ->whereNull('species.id')
        ->count();

    $orphanMoves = DB::table('learnsets')
        ->leftJoin('moves', 'moves.id', '=', 'learnsets.move_id')
        ->whereNull('moves.id')
        ->count();

    expect($orphanSpecies)->toBe(0)->and($orphanMoves)->toBe(0);
});

it('da un learnset no vacio a toda especie legal', function () {
    $withoutMoves = DB::table('legality')
        ->where('regulation_id', regulationId('M-C'))
        ->where('entity_type', 'species')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('learnsets')
                ->whereColumn('learnsets.species_id', 'legality.entity_id')
                ->whereColumn('learnsets.regulation_id', 'legality.regulation_id');
        })
        ->count();

    expect($withoutMoves)->toBe(0);
});

it('resuelve las formas base referenciadas', function () {
    $broken = DB::table('species as s')
        ->leftJoin('species as base', 'base.id', '=', 's.base_form_id')
        ->whereNotNull('s.base_form_id')
        ->whereNull('base.id')
        ->count();

    expect($broken)->toBe(0);

    $floetteMega = DB::table('species')->where('slug', 'floettemega')->first();
    expect($floetteMega)->not->toBeNull()
        ->and($floetteMega->base_form_id)->not->toBeNull();
});

it('marca como no construibles las formas de transformacion en combate', function () {
    foreach (['miniormeteor', 'eiscuenoice', 'meloettapirouette'] as $slug) {
        $row = DB::table('species')->where('slug', $slug)->first();
        expect($row)->not->toBeNull()
            ->and($row->is_buildable)->toBeFalse();
    }

    expect(DB::table('species')->where('slug', 'floette')->value('is_buildable'))->toBeFalse()
        ->and(DB::table('species')->where('slug', 'floetteeternal')->value('is_buildable'))->toBeTrue();
});

it('refleja las altas de la regulacion M-C', function () {
    foreach (['rillaboom', 'cinderace', 'inteleon'] as $slug) {
        expect(isLegal('M-C', 'species', $slug))->toBeTrue()
            ->and(isLegal('M-B', 'species', $slug))->toBeFalse();
    }
});

it('detecta Fake Out en los usuarios clasicos', function () {
    expect(learns('M-C', 'incineroar', 'fakeout'))->toBeTrue()
        ->and(learns('M-C', 'rillaboom', 'fakeout'))->toBeTrue();
});

it('detecta Intimidate en Incineroar', function () {
    $abilities = DB::table('species')->where('slug', 'incineroar')->value('abilities');

    expect(array_values(json_decode($abilities, true)))->toContain('intimidate');
});

it('detecta Good as Gold en Gholdengo', function () {
    $abilities = DB::table('species')->where('slug', 'gholdengo')->value('abilities');

    expect(array_values(json_decode($abilities, true)))->toContain('goodasgold');
});

it('detecta Armor Tail en Farigiraf', function () {
    $abilities = DB::table('species')->where('slug', 'farigiraf')->value('abilities');

    expect(array_values(json_decode($abilities, true)))->toContain('armortail');
});

it('conserva el objetivo de los movimientos de area, que es clave en dobles', function () {
    expect(DB::table('moves')->where('slug', 'makeitrain')->value('target'))->toBe('allAdjacentFoes')
        ->and(DB::table('moves')->where('slug', 'dazzlinggleam')->value('target'))->toBe('allAdjacentFoes')
        ->and(DB::table('moves')->where('slug', 'fakeout')->value('target'))->toBe('normal')
        ->and(DB::table('moves')->where('slug', 'fakeout')->value('priority'))->toBe(3);
});

it('guarda las mega evoluciones como mapa base to mega', function () {
    $abomasite = DB::table('items')->where('slug', 'abomasite')->first();

    expect($abomasite->is_mega_stone)->toBeTrue()
        ->and(json_decode($abomasite->mega_evolutions, true))->toBe(['abomasnow' => 'abomasnowmega']);
});

it('rechaza en base de datos un spread que supere 66 SP', function () {
    $ids = seedTeamFixture();

    expect(fn () => DB::table('team_slots')->insert([
        'team_id' => $ids['team'],
        'position' => 1,
        'species_id' => $ids['species'],
        'sp_hp' => 32,
        'sp_atk' => 32,
        'sp_def' => 32,
        'sp_spa' => 0,
        'sp_spd' => 0,
        'sp_spe' => 0,
        'moves' => json_encode(['fakeout']),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('rechaza en base de datos mas de 32 SP en una estadistica', function () {
    $ids = seedTeamFixture();

    expect(fn () => DB::table('team_slots')->insert([
        'team_id' => $ids['team'],
        'position' => 1,
        'species_id' => $ids['species'],
        'sp_hp' => 0,
        'sp_atk' => 33,
        'sp_def' => 0,
        'sp_spa' => 0,
        'sp_spd' => 0,
        'sp_spe' => 0,
        'moves' => json_encode(['fakeout']),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('rechaza en base de datos mas de 4 movimientos', function () {
    $ids = seedTeamFixture();

    expect(fn () => DB::table('team_slots')->insert([
        'team_id' => $ids['team'],
        'position' => 1,
        'species_id' => $ids['species'],
        'sp_hp' => 0,
        'sp_atk' => 0,
        'sp_def' => 0,
        'sp_spa' => 0,
        'sp_spd' => 0,
        'sp_spe' => 0,
        'moves' => json_encode(['fakeout', 'knockoff', 'partingshot', 'flareblitz', 'uturn']),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

function seedTeamFixture(): array
{
    $regulationId = regulationId('M-C');
    $formatId = DB::table('formats')->where('showdown_id', 'gen9championsvgc2026regmc')->value('id');

    $teamId = DB::table('teams')->insertGetId([
        'regulation_id' => $regulationId,
        'format_id' => $formatId,
        'name' => 'fixture',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'team' => $teamId,
        'species' => DB::table('species')->where('slug', 'incineroar')->value('id'),
    ];
}
