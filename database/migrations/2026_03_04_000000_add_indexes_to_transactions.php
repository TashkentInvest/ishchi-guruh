<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            $table->index('district', 'idx_district');
            $table->index('year', 'idx_year');
            $table->index('month', 'idx_month');
            $table->index('type', 'idx_type');
            $table->index('flow', 'idx_flow');
            $table->index('date', 'idx_date');

            // Composite indexes for common query patterns
            $table->index(['year', 'month'], 'idx_year_month');
            $table->index(['district', 'flow'], 'idx_district_flow');
            $table->index(['date', 'flow'], 'idx_date_flow');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_district');
            $table->dropIndex('idx_year');
            $table->dropIndex('idx_month');
            $table->dropIndex('idx_type');
            $table->dropIndex('idx_flow');
            $table->dropIndex('idx_date');
            $table->dropIndex('idx_year_month');
            $table->dropIndex('idx_district_flow');
            $table->dropIndex('idx_date_flow');
        });
    }
};
