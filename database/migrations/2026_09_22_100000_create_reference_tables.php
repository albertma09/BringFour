<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('source_version')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('showdown_id')->unique();
            $table->string('battle_type');
            $table->unsignedSmallInteger('team_size_min');
            $table->unsignedSmallInteger('team_size_max');
            $table->unsignedSmallInteger('bring_count')->nullable();
            $table->boolean('best_of_three')->default(false);
            $table->boolean('collect_replays')->default(true);
            $table->boolean('process_replays')->default(true);
            $table->timestamps();

            $table->unique(['regulation_id', 'slug']);
        });

        Schema::create('species', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('national_dex')->nullable();
            $table->jsonb('types');
            $table->jsonb('base_stats');
            $table->jsonb('abilities');
            $table->boolean('is_mega')->default(false);
            $table->boolean('is_buildable')->default(true);
            $table->foreignId('base_form_id')->nullable()->constrained('species')->nullOnDelete();
            $table->string('required_item_slug')->nullable();
            $table->decimal('weight_kg', 6, 1)->nullable();
            $table->timestamps();

            $table->index('is_mega');
            $table->index('is_buildable');
        });

        Schema::create('moves', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type');
            $table->string('category');
            $table->unsignedSmallInteger('power')->nullable();
            $table->unsignedSmallInteger('accuracy')->nullable();
            $table->unsignedSmallInteger('pp');
            $table->smallInteger('priority')->default(0);
            $table->string('target');
            $table->jsonb('flags')->nullable();
            $table->jsonb('secondary')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('target');
        });

        Schema::create('abilities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('effect')->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('effect')->nullable();
            $table->boolean('is_mega_stone')->default(false);
            $table->jsonb('mega_evolutions')->nullable();
            $table->timestamps();

            $table->index('is_mega_stone');
        });

        Schema::create('alignments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('plus_stat')->nullable();
            $table->string('minus_stat')->nullable();
            $table->timestamps();
        });

        Schema::create('learnsets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('species_id')->constrained()->cascadeOnDelete();
            $table->foreignId('move_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['regulation_id', 'species_id', 'move_id']);
            $table->index(['regulation_id', 'species_id']);
            $table->index(['regulation_id', 'move_id']);
        });

        Schema::create('legality', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->boolean('is_legal')->default(true);
            $table->string('restriction')->nullable();
            $table->timestamps();

            $table->unique(['regulation_id', 'entity_type', 'entity_id']);
            $table->index(['regulation_id', 'entity_type', 'is_legal']);
        });

        DB::statement('CREATE INDEX species_types_gin ON species USING gin (types jsonb_path_ops)');
        DB::statement('CREATE INDEX species_abilities_gin ON species USING gin (abilities jsonb_path_ops)');
        DB::statement('CREATE INDEX moves_flags_gin ON moves USING gin (flags jsonb_path_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('legality');
        Schema::dropIfExists('learnsets');
        Schema::dropIfExists('alignments');
        Schema::dropIfExists('items');
        Schema::dropIfExists('abilities');
        Schema::dropIfExists('moves');
        Schema::dropIfExists('species');
        Schema::dropIfExists('formats');
        Schema::dropIfExists('regulations');
    }
};
