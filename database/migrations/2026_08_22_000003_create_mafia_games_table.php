<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Finished-game history only: active-game truth lives in Redis snapshots.
        Schema::create('mafia_games', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('room_id');
            $table->string('bot_id', 20)->nullable();
            $table->bigInteger('chat_id')->nullable();
            $table->char('locale', 2)->default('en');
            $table->string('state', 24)->default('ended');
            $table->unsignedSmallInteger('day_count')->default(0);
            $table->string('winner', 24)->nullable();
            $table->json('config')->nullable();
            $table->timestampsTz();

            $table->foreign('room_id')->references('id')->on('mafia_rooms')->cascadeOnDelete();
        });

        Schema::create('mafia_players', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->string('user_id', 64);
            $table->string('username');
            $table->boolean('is_bot')->default(false);
            $table->string('role', 24)->nullable();
            $table->boolean('is_alive')->default(true);
            $table->boolean('missed_vote')->default(false);
            $table->timestampsTz();

            $table->foreign('game_id')->references('id')->on('mafia_games')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mafia_players');
        Schema::dropIfExists('mafia_games');
    }
};
