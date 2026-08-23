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
        Schema::create(mafia_games, function (Blueprint ): void {
            ->uuid(id)->primary();
            ->uuid(room_id);
            ->string(bot_id, 20)->nullable();
            ->bigInteger(chat_id)->nullable();
            ->char(locale, 2)->default(en);
            ->string(state, 24)->default(ended);
            ->unsignedSmallInteger(day_count)->default(0);
            ->string(winner, 24)->nullable();
            ->json(config)->nullable();
            ->timestampsTz();

            ->foreign(room_id)->references(id)->on(mafia_rooms)->cascadeOnDelete();
        });

        Schema::create(mafia_players, function (Blueprint ): void {
            ->uuid(id)->primary();
            ->uuid(game_id);
            ->string(user_id, 64);
            ->string(username);
            ->boolean(is_bot)->default(false);
            ->string(role, 24)->nullable();
            ->boolean(is_alive)->default(true);
            ->boolean(missed_vote)->default(false);
            ->timestampsTz();

            ->foreign(game_id)->references(id)->on(mafia_games)->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(mafia_players);
        Schema::dropIfExists(mafia_games);
    }
};
