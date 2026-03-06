<?php

namespace App\Console\Commands;

use App\Http\Controllers\TransactionController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WarmReportCache extends Command
{
    protected $signature   = 'cache:warm-reports';
    protected $description = 'Pre-compute and cache all heavy transaction report aggregations (all/jamgarma/gazna)';

    public function handle(): int
    {
        $this->info('Warming report caches...');
        $start = microtime(true);

        foreach (['all', 'jamgarma', 'gazna'] as $suffix) {
            Cache::forget("transaction_filters_{$suffix}");
            Cache::forget("transaction_summary_{$suffix}");
            Cache::forget("summary_report_data_{$suffix}");
            Cache::forget("summary2_report_data_{$suffix}");
            Cache::forget("dashboard_data_{$suffix}");
        }

        Cache::forget('transaction_filters');
        Cache::forget('transaction_summary');
        Cache::forget('summary_report_data');
        Cache::forget('summary2_report_data');
        Cache::forget('dashboard_data');

        /** @var TransactionController $controller */
        $controller = app(TransactionController::class);

        foreach ([null, 'jamgarma', 'gazna'] as $status) {
            $query = $status ? ['status' => $status] : [];

            $controller->index(Request::create('/home', 'GET', $query));
            $controller->dashboard(Request::create('/dashboard', 'GET', $query));
            $controller->summary(Request::create('/summary', 'GET', $query));
            $controller->summary2(Request::create('/summary2', 'GET', $query));

            $this->line('  ✓ ' . ($status ?? 'all'));
        }

        $elapsed = round((microtime(true) - $start) * 1000);
        $this->info("All caches warmed in {$elapsed}ms");

        return self::SUCCESS;
    }
}
