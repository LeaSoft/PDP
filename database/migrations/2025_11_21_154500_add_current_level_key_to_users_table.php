<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'current_level_key')) {
                $table->string('current_level_key')->nullable()->after('pro_level_key')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'current_level_key')) {
                $table->dropIndex(['current_level_key']);
                $table->dropColumn('current_level_key');
            }
        });
    }
};
