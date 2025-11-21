<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pro_level_key')) {
                $table->string('pro_level_key')->nullable()->after('is_moderator')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pro_level_key')) {
                $table->dropIndex(['pro_level_key']);
                $table->dropColumn('pro_level_key');
            }
        });
    }
};
