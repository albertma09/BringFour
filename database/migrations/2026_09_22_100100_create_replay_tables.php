<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replays', function (Blueprint $table) {
            $table->id();
            $table->string('showdown_id')->unique();
            $table->foreignId('format_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('uploaded_at');
            $table->char('p1_hash', 64);
            $table->char('p2_hash', 64);
            $table->unsignedSmallInteger('p1_rating')->nullable();
            $table->unsignedSmallInteger('p2_rating')->nullable();
            $table->unsignedSmallInteger('elo_bucket')->nullable();
            $table->string('winner_side')->nullable();
            $table->unsignedSmallInteger('turn_count')->nullable();
            $table->boolean('rated')->default(false);
            $table->boolean('open_team_sheets')->default(false);
            $table->binary('raw_log');
            $table->char('raw_log_sha256', 64);
            $table->timestampTz('parsed_at')->nullable();
            $table->string('parser_version')->nullable();
            $table->timestamps();

            $table->index(['regulation_id', 'uploaded_at']);
            $table->index(['format_id', 'elo_bucket']);
            $table->index('parsed_at');
        });

        Schema::create('replay_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replay_id')->constrained()->cascadeOnDelete();
            $table->string('side');
            $table->foreignId('species_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('preview_position');
            $table->boolean('brought')->default(false);
            $table->boolean('lead')->default(false);
            $table->timestamps();

            $table->unique(['replay_id', 'side', 'preview_position']);
            $table->index(['species_id', 'brought']);
            $table->index(['replay_id', 'side']);
        });

        Schema::create('replay_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replay_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('turn_no');
            $table->unsignedSmallInteger('decision_seconds')->nullable();
            $table->jsonb('field_state')->nullable();
            $table->timestamps();

            $table->unique(['replay_id', 'turn_no']);
        });

        Schema::create('replay_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replay_turn_id')->constrained()->cascadeOnDelete();
            $table->string('side');
            $table->string('slot');
            $table->string('action_type');
            $table->foreignId('actor_species_id')->nullable()->constrained('species')->nullOnDelete();
            $table->unsignedSmallInteger('actor_hp_pct')->nullable();
            $table->foreignId('move_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_side')->nullable();
            $table->string('target_slot')->nullable();
            $table->foreignId('revealed_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('revealed_ability_id')->nullable()->constrained('abilities')->nullOnDelete();
            $table->timestamps();

            $table->index(['replay_turn_id', 'side']);
            $table->index(['actor_species_id', 'action_type']);
        });

        Schema::create('behavior_priors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('format_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('elo_bucket');
            $table->string('prior_type');
            $table->char('context_hash', 64);
            $table->jsonb('context');
            $table->string('action_key');
            $table->jsonb('action');
            $table->unsignedInteger('n');
            $table->decimal('pct', 5, 2);
            $table->timestampTz('computed_at');

            $table->unique(['context_hash', 'elo_bucket', 'action_key']);
            $table->index(['regulation_id', 'prior_type', 'elo_bucket']);
        });

        DB::statement('CREATE INDEX replay_turns_field_state_gin ON replay_turns USING gin (field_state jsonb_path_ops)');
        DB::statement('CREATE INDEX behavior_priors_context_gin ON behavior_priors USING gin (context jsonb_path_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_priors');
        Schema::dropIfExists('replay_actions');
        Schema::dropIfExists('replay_turns');
        Schema::dropIfExists('replay_teams');
        Schema::dropIfExists('replays');
    }
};
