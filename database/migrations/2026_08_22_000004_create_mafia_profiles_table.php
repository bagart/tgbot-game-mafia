<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mafia_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('bot_id', 20);
            $table->string('user_id', 64);
            $table->unsignedSmallInteger('consecutive_skips')->default(0);
            $table->timestampTz('frozen_until')->nullable();
            $table->unsignedInteger('sleepy_total')->default(0);
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->string('favorite_role', 24)->nullable();
            $table->timestampsTz();

            $table->unique(['bot_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mafia_profiles');
    }
};
