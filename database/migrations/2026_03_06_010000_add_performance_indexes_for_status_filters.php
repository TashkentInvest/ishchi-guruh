<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $this->createIndexIfMissing('users', 'users_status_created_at_idx', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'users_status_created_at_idx');
            });

            $this->createIndexIfMissing('users', 'users_role_status_created_at_idx', function (Blueprint $table) {
                $table->index(['role', 'status', 'created_at'], 'users_role_status_created_at_idx');
            });
        }

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'status')) {
            $this->createIndexIfMissing('transactions', 'idx_cov_status_date_flow_amount', function (Blueprint $table) {
                $table->index(['status', 'date', 'flow', 'amount'], 'idx_cov_status_date_flow_amount');
            });

            $this->createIndexIfMissing('transactions', 'idx_cov_status_district_flow', function (Blueprint $table) {
                $table->index(['status', 'district', 'flow'], 'idx_cov_status_district_flow');
            });

            $this->createIndexIfMissing('transactions', 'idx_cov_status_type_flow', function (Blueprint $table) {
                $table->index(['status', 'type', 'flow'], 'idx_cov_status_type_flow');
            });

            $this->createIndexIfMissing('transactions', 'idx_cov_status_district_year_month', function (Blueprint $table) {
                $table->index(['status', 'district', 'year', 'month'], 'idx_cov_status_district_year_month');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            $this->dropIndexIfExists('users', 'users_status_created_at_idx');
            $this->dropIndexIfExists('users', 'users_role_status_created_at_idx');
        }

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'status')) {
            $this->dropIndexIfExists('transactions', 'idx_cov_status_date_flow_amount');
            $this->dropIndexIfExists('transactions', 'idx_cov_status_district_flow');
            $this->dropIndexIfExists('transactions', 'idx_cov_status_type_flow');
            $this->dropIndexIfExists('transactions', 'idx_cov_status_district_year_month');
        }
    }

    private function createIndexIfMissing(string $tableName, string $indexName, callable $definition): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (!$this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $databaseName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(1) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$databaseName, $tableName, $indexName]
        );

        return ((int) ($result->aggregate ?? 0)) > 0;
    }
};
