<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mafia_room_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('room_id');
            $table->string('user_id', 64);
            $table->string('name');
            $table->boolean('is_bot')->default(false);
            $table->string('state', 16)->default('joined');
            $table->timestampsTz();

            $table->unique(['room_id', 'user_id']);
            $table->foreign('room_id')->references('id')->on('mafia_rooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mafia_room_members');
    }
};
