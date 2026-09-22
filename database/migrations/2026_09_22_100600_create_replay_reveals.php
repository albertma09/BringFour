<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replay_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replay_id')->constrained()->cascadeOnDelete();
            $table->string('side');
            $table->foreignId('species_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->foreignId('ability_id')->nullable()->constrained('abilities')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->unsignedSmallInteger('turn_no')->nullable();

            $table->unique(['replay_id', 'side', 'species_id', 'kind']);
            $table->index(['species_id', 'kind']);
        });

        Schema::table('replay_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revealed_item_id');
            $table->dropConstrainedForeignId('revealed_ability_id');
        });
    }

    public function down(): void
    {
        Schema::table('replay_actions', function (Blueprint $table) {
            $table->foreignId('revealed_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('revealed_ability_id')->nullable()->constrained('abilities')->nullOnDelete();
        });

        Schema::dropIfExists('replay_reveals');
    }
};
