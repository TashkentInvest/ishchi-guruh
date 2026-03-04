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
        if (!Schema::hasTable('transactions')) {
            return; // Transactions table not yet created, skip
        }

        $indexes = [
            ['columns' => ['district'],        'name' => 'idx_district'],
            ['columns' => ['year'],             'name' => 'idx_year'],
            ['columns' => ['month'],            'name' => 'idx_month'],
            ['columns' => ['type'],             'name' => 'idx_type'],
            ['columns' => ['flow'],             'name' => 'idx_flow'],
            ['columns' => ['date'],             'name' => 'idx_date'],
            ['columns' => ['year', 'month'],    'name' => 'idx_year_month'],
            ['columns' => ['district', 'flow'], 'name' => 'idx_district_flow'],
            ['columns' => ['date', 'flow'],     'name' => 'idx_date_flow'],
        ];

        foreach ($indexes as $index) {
            try {
                Schema::table('transactions', function (Blueprint $table) use ($index) {
                    $table->index($index['columns'], $index['name']);
                });
            } catch (\Exception $e) {
                // Index already exists — skip
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $indexes = ['idx_district','idx_year','idx_month','idx_type','idx_flow',
                    'idx_date','idx_year_month','idx_district_flow','idx_date_flow'];

        foreach ($indexes as $idx) {
            try {
                Schema::table('transactions', function (Blueprint $table) use ($idx) {
                    $table->dropIndex($idx);
                });
            } catch (\Exception $e) {
                // Index doesn't exist — skip
            }
        }
    }
};
