<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'status')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('status', 20)->default('jamgarma')->after('day_date');
                $table->index('status');
            });
        }

        if (Schema::hasTable('formulas') && !Schema::hasColumn('formulas', 'status')) {
            Schema::table('formulas', function (Blueprint $table) {
                $table->string('status', 20)->default('jamgarma')->after('district_code');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'status')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }

        if (Schema::hasTable('formulas') && Schema::hasColumn('formulas', 'status')) {
            Schema::table('formulas', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }
    }
};
