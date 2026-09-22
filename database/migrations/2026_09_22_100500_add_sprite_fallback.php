<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->string('sprite_file')->nullable()->after('sprite_id');
            $table->string('sprite_stone_slug')->nullable()->after('sprite_file');
        });
    }

    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn(['sprite_file', 'sprite_stone_slug']);
        });
    }
};
