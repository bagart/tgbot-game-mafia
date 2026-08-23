<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(mafia_room_members, function (Blueprint ): void {
            ->uuid(id)->primary();
            ->uuid(room_id);
            ->string(user_id, 64);
            ->string(name);
            ->boolean(is_bot)->default(false);
            ->string(state, 16)->default(joined);
            ->timestampsTz();

            ->unique([room_id, user_id]);
            ->foreign(room_id)->references(id)->on(mafia_rooms)->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(mafia_room_members);
    }
};
