<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mafia_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 16);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 16)->default('lobby');
            $table->string('title');
            $table->string('host_user_id', 64);
            $table->string('chat_id', 32)->nullable();
            $table->string('bot_id', 20)->nullable();
            $table->unsignedSmallInteger('night_seconds')->nullable();
            $table->unsignedSmallInteger('discussion_seconds')->nullable();
            $table->unsignedSmallInteger('vote_seconds')->nullable();
            $table->unsignedTinyInteger('min_players');
            $table->unsignedTinyInteger('max_players');
            $table->json('role_config')->nullable();
            $table->char('locale', 2)->default('en');
            $table->uuid('last_game_id')->nullable();
            $table->timestampsTz();

            $table->index(['chat_id', 'status']);
            $table->index(['status', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mafia_rooms');
    }
};
