<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_copy_reviews', function (Blueprint $table) {
            $table->unsignedSmallInteger('playthrough_count')->nullable()->after('hours_played');
            $table->boolean('would_replay')->nullable()->after('playthrough_count');
            $table->boolean('would_recommend')->nullable()->after('would_replay');
        });
    }

    public function down(): void
    {
        Schema::table('game_copy_reviews', function (Blueprint $table) {
            $table->dropColumn(['playthrough_count', 'would_replay', 'would_recommend']);
        });
    }
};
