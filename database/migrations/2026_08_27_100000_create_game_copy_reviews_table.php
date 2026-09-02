<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_copy_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('game_copy_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('game_base_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('play_status', 20)->default('backlog');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->decimal('hours_played', 6, 1)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['game_copy_id', 'play_status']);
            $table->index(['user_id', 'game_copy_id']);
        });

        DB::table('game_copies')
            ->select('id', 'user_id', 'game_base_id', 'notes')
            ->orderBy('id')
            ->chunkById(500, function ($copies) {
                DB::table('game_copy_reviews')->insert($copies->map(fn ($copy) => [
                    'user_id' => $copy->user_id,
                    'game_copy_id' => $copy->id,
                    'game_base_id' => $copy->game_base_id,
                    'play_status' => 'backlog',
                    'notes' => $copy->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_copy_reviews');
    }
};
