<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        if (app()->isLocal() || app()->environment('testing') || config('app.debug')) {
            Model::shouldBeStrict();
        }

        if ($this->shouldLogSlowQueries()) {
            $thresholdMs = max(1, (int) config('database.slow_query_time_ms', 250));
            $channel = (string) config('database.slow_query_log_channel', 'slow_queries');

            DB::listen(function (QueryExecuted $query) use ($thresholdMs, $channel) {
                if ($query->time < $thresholdMs) {
                    return;
                }

                Log::channel($channel)->warning('Slow query detected', [
                    'time_ms' => round($query->time, 2),
                    'connection' => $query->connectionName,
                    'sql' => $query->sql,
                    'bindings_count' => count($query->bindings),
                ]);
            });
        }
    }

    private function shouldLogSlowQueries(): bool
    {
        return app()->isProduction() && (bool) config('database.log_slow_queries', false);
    }
}
