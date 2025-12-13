<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pdp_skill_criterion_progress', function (Blueprint $table) {
            // Add status column: pending, approved, rejected
            $table->string('status', 20)->default('pending')->after('approved');

            // Migrate existing data: if approved=true then status='approved', else 'pending'
            // Will be done via raw SQL after column is added
        });

        // Migrate existing data
        DB::statement("UPDATE pdp_skill_criterion_progress SET status = 'approved' WHERE approved = true");
        DB::statement("UPDATE pdp_skill_criterion_progress SET status = 'pending' WHERE approved = false");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdp_skill_criterion_progress', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
