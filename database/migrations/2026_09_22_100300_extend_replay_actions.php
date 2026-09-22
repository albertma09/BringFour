<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replay_actions', function (Blueprint $table) {
            $table->foreignId('switch_in_species_id')->nullable()->after('actor_species_id')->constrained('species')->nullOnDelete();
            $table->boolean('forced')->default(false)->after('action_type');
            $table->string('reason')->nullable()->after('forced');
        });
    }

    public function down(): void
    {
        Schema::table('replay_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('switch_in_species_id');
            $table->dropColumn(['forced', 'reason']);
        });
    }
};
