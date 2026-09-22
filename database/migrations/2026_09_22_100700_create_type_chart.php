<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        });

        Schema::create('type_effectiveness', function (Blueprint $table) {
            $table->string('attacker');
            $table->string('defender');
            $table->decimal('multiplier', 3, 2);

            $table->primary(['attacker', 'defender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('type_effectiveness');
        Schema::dropIfExists('types');
    }
};
