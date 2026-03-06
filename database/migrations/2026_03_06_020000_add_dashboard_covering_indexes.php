<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            $this->createIndexIfMissing('transactions', 'idx_cov_district_flow_amount', function (Blueprint $table) {
                $table->index(['district', 'flow', 'amount'], 'idx_cov_district_flow_amount');
            });

            $this->createIndexIfMissing('transactions', 'idx_cov_type_flow_amount', function (Blueprint $table) {
                $table->index(['type', 'flow', 'amount'], 'idx_cov_type_flow_amount');
            });

            $this->createIndexIfMissing('transactions', 'idx_cov_status_flow_amount', function (Blueprint $table) {
                $table->index(['status', 'flow', 'amount'], 'idx_cov_status_flow_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            $this->dropIndexIfExists('transactions', 'idx_cov_district_flow_amount');
            $this->dropIndexIfExists('transactions', 'idx_cov_type_flow_amount');
            $this->dropIndexIfExists('transactions', 'idx_cov_status_flow_amount');
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
