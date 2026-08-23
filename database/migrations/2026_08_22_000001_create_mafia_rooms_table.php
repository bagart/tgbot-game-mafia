<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(mafia_rooms, function (Blueprint ): void {
            ->uuid(id)->primary();
            ->string(kind, 16);
            ->string(visibility, 16)->default(private);
            ->string(status, 16)->default(lobby);
            ->string(title);
            ->string(host_user_id, 64);
            ->string(chat_id, 32)->nullable();
            ->unsignedTinyInteger(min_players);
            ->unsignedTinyInteger(max_players);
            ->json(role_config)->nullable();
            ->char(locale, 2)->default(en);
            ->uuid(last_game_id)->nullable();
            ->timestampsTz();

            ->index([chat_id, status]);
            ->index([status, visibility]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(mafia_rooms);
    }
};
