<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering composite indexes for all heavy query patterns on transactions table.
     *
     * Query patterns covered:
     *  - summary():  GROUP BY district WHERE flow='Приход'  → (flow, district, type, amount)
     *  - dashboard(): aggregate by date range              → (date, flow, amount)
     *  - summary2(): GROUP BY YEAR(date), MONTH(date)      → (date, flow, amount)
     *  - index():    paginate with district/year/type/flow  → (district, year, flow, type)
     *  - FULLTEXT on payment_purpose for LIKE search
     */
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $raw = [
            // Covers summary(): WHERE district IS NOT NULL + GROUP BY district, filtered by flow + type + amount
            'idx_cov_district_flow_type_amount' =>
                'CREATE INDEX idx_cov_district_flow_type_amount ON transactions (district, flow, type, amount)',

            // Covers dashboard() date-range + flow aggregation
            'idx_cov_date_flow_amount' =>
                'CREATE INDEX idx_cov_date_flow_amount ON transactions (date, flow, amount)',

            // Covers index() filtered pagination: year + month + district + type
            'idx_cov_year_month_district_flow' =>
                'CREATE INDEX idx_cov_year_month_district_flow ON transactions (year, month, district, flow)',

            // Covers summary2(): YEAR(date)/MONTH(date) group — date prefix is sufficient
            // date, flow, amount already covered by idx_cov_date_flow_amount

            // Covers flow-only aggregates (dashboard overall summary)
            'idx_cov_flow_amount' =>
                'CREATE INDEX idx_cov_flow_amount ON transactions (flow, amount)',
        ];

        foreach ($raw as $name => $sql) {
            try {
                // Check if index already exists
                $exists = DB::select("
                    SELECT COUNT(*) as cnt
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = 'transactions'
                      AND index_name = ?
                ", [$name]);

                if ($exists[0]->cnt == 0) {
                    DB::statement($sql);
                }
            } catch (\Exception $e) {
                // Skip duplicates silently
            }
        }

        // FULLTEXT index for payment_purpose search (replaces LIKE '%...%' full-table scan)
        try {
            $exists = DB::select("
                SELECT COUNT(*) as cnt
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'transactions'
                  AND index_name = 'idx_ft_payment_purpose'
            ");
            if ($exists[0]->cnt == 0) {
                DB::statement('ALTER TABLE transactions ADD FULLTEXT INDEX idx_ft_payment_purpose (payment_purpose)');
            }
        } catch (\Exception $e) {
            // MyISAM-only or already exists
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $indexes = [
            'idx_cov_district_flow_type_amount',
            'idx_cov_date_flow_amount',
            'idx_cov_year_month_district_flow',
            'idx_cov_flow_amount',
            'idx_ft_payment_purpose',
        ];

        foreach ($indexes as $idx) {
            try {
                DB::statement("DROP INDEX {$idx} ON transactions");
            } catch (\Exception $e) {
                // Already gone
            }
        }
    }
};
