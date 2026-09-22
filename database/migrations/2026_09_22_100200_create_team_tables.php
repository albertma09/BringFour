<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('format_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('visibility')->default('private');
            $table->string('share_slug')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'regulation_id']);
        });

        Schema::create('team_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('species_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ability_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('alignment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sp_hp')->default(0);
            $table->unsignedSmallInteger('sp_atk')->default(0);
            $table->unsignedSmallInteger('sp_def')->default(0);
            $table->unsignedSmallInteger('sp_spa')->default(0);
            $table->unsignedSmallInteger('sp_spd')->default(0);
            $table->unsignedSmallInteger('sp_spe')->default(0);
            $table->jsonb('moves');
            $table->string('nickname')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'position']);
        });

        DB::statement('ALTER TABLE team_slots ADD CONSTRAINT team_slots_sp_total CHECK (sp_hp + sp_atk + sp_def + sp_spa + sp_spd + sp_spe <= 66)');
        DB::statement('ALTER TABLE team_slots ADD CONSTRAINT team_slots_sp_cap CHECK (sp_hp <= 32 AND sp_atk <= 32 AND sp_def <= 32 AND sp_spa <= 32 AND sp_spd <= 32 AND sp_spe <= 32)');
        DB::statement('ALTER TABLE team_slots ADD CONSTRAINT team_slots_moves_count CHECK (jsonb_array_length(moves) BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        Schema::dropIfExists('team_slots');
        Schema::dropIfExists('teams');
    }
};
