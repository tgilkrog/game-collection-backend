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
        Schema::table('game_bases', function (Blueprint $table) {
            $table->integer('rawg_id')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_bases', function (Blueprint $table) {
            $table->dropUnique(['rawg_id']);
            $table->dropColumn('rawg_id');
        });
    }
};
