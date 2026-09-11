<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('mafia_profiles', function (Blueprint $table): void {
            $table->json('flags')->nullable()->after('favorite_role');
            $table->char('preferred_locale', 2)->nullable()->after('flags');
        });
    }

    public function down(): void
    {
        Schema::table('mafia_profiles', function (Blueprint $table): void {
            $table->dropColumn(['flags', 'preferred_locale']);
        });
    }
};
