<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(mafia_profiles, function (Blueprint ): void {
            ->uuid(id)->primary();
            ->string(bot_id, 20);
            ->string(user_id, 64);
            ->unsignedSmallInteger(consecutive_skips)->default(0);
            ->timestampTz(frozen_until)->nullable();
            ->unsignedInteger(sleepy_total)->default(0);
            ->unsignedInteger(games_played)->default(0);
            ->unsignedInteger(wins)->default(0);
            ->string(favorite_role, 24)->nullable();
            ->timestampsTz();

            ->unique([bot_id, user_id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(mafia_profiles);
    }
};
