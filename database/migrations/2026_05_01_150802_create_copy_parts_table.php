<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('copy_parts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_copy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');

            $table->foreignId('condition_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copy_parts');
    }
};
