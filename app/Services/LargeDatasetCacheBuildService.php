<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LargeDatasetCacheBuildService
{
    private const STATE_KEY = 'dataset_build:state';
    private const LOCK_KEY = 'dataset_build:lock';
    private const AGGREGATE_KEY = 'dataset_build:aggregate';
    private const RESULT_KEY = 'dataset_build:result';
    private const TICK_LOCK_KEY = 'dataset_build:tick_mutex';

    private const CACHE_TTL_SECONDS = 7200;
    private const STALE_AFTER_SECONDS = 300;

    private const MIN_CHUNK_SIZE = 100;
    private const MAX_CHUNK_SIZE = 500;
    private const DEFAULT_CHUNK_SIZE = 250;

    public function start(?int $chunkSize = null): array
    {
        $chunkSize = $this->normalizeChunkSize($chunkSize);

        $existingState = Cache::get(self::STATE_KEY);
        if (is_array($existingState) && ($existingState['status'] ?? null) === 'running') {
            if (!$this->isStale($existingState)) {
                $existingState['already_running'] = true;
                return $existingState;
            }

            $this->forceReset();
        }

        $processId = (string) Str::uuid();
        $lockAcquired = Cache::add(
            self::LOCK_KEY,
            $processId,
            now()->addSeconds(self::CACHE_TTL_SECONDS)
        );

        if (!$lockAcquired) {
            $state = Cache::get(self::STATE_KEY, $this->idleState());
            if (is_array($state)) {
                $state['already_running'] = true;
                return $state;
            }

            return $this->idleState();
        }

        DB::connection()->disableQueryLog();

        $total = (int) DB::table('transactions')->count();

        $state = [
            'process_id' => $processId,
            'status' => 'running',
            'message' => 'Processing started',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'completed_at' => null,
            'chunk_size' => $chunkSize,
            'cursor_id' => 0,
            'processed' => 0,
            'total' => $total,
            'percent' => 0,
            'already_running' => false,
            'result' => null,
        ];

        Cache::put(self::STATE_KEY, $state, now()->addSeconds(self::CACHE_TTL_SECONDS));
        Cache::put(self::AGGREGATE_KEY, $this->blankAggregate(), now()->addSeconds(self::CACHE_TTL_SECONDS));
        Cache::forget(self::RESULT_KEY);

        if ($total === 0) {
            return $this->finalize($state, $this->blankAggregate());
        }

        return $state;
    }

    public function progress(bool $advance = true): array
    {
        $state = Cache::get(self::STATE_KEY);

        if (!is_array($state)) {
            return $this->idleState();
        }

        if (($state['status'] ?? null) !== 'running' || !$advance) {
            return $this->attachResultIfAvailable($state);
        }

        $tickLock = Cache::lock(self::TICK_LOCK_KEY, 10);
        if (!$tickLock->get()) {
            return $this->attachResultIfAvailable($state);
        }

        try {
            $freshState = Cache::get(self::STATE_KEY, $state);

            if (!is_array($freshState) || ($freshState['status'] ?? null) !== 'running') {
                return $this->attachResultIfAvailable(is_array($freshState) ? $freshState : $this->idleState());
            }

            return $this->advanceOneChunk($freshState);
        } finally {
            optional($tickLock)->release();
        }
    }

    public function reset(): array
    {
        $this->forceReset();

        return $this->idleState();
    }

    private function advanceOneChunk(array $state): array
    {
        DB::connection()->disableQueryLog();
        set_time_limit(0);

        $chunkSize = $this->normalizeChunkSize((int) ($state['chunk_size'] ?? self::DEFAULT_CHUNK_SIZE));
        $cursorId = (int) ($state['cursor_id'] ?? 0);

        $rows = DB::table('transactions')
            ->select(['id', 'status', 'district', 'flow', 'amount', 'year', 'month'])
            ->where('id', '>', $cursorId)
            ->orderBy('id')
            ->limit($chunkSize)
            ->get();

        if ($rows->isEmpty()) {
            $aggregate = Cache::get(self::AGGREGATE_KEY, $this->blankAggregate());

            return $this->finalize($state, is_array($aggregate) ? $aggregate : $this->blankAggregate());
        }

        $aggregate = Cache::get(self::AGGREGATE_KEY, $this->blankAggregate());
        if (!is_array($aggregate)) {
            $aggregate = $this->blankAggregate();
        }

        $processedInChunk = 0;

        foreach ($rows as $row) {
            $rowAmount = (float) ($row->amount ?? 0);
            $flow = mb_strtolower(trim((string) ($row->flow ?? '')));
            $isCredit = str_contains($flow, 'приход');

            if ($isCredit) {
                $aggregate['total_credit'] += $rowAmount;
            } else {
                $aggregate['total_debit'] += $rowAmount;
            }

            $aggregate['records'] += 1;

            $status = trim((string) ($row->status ?? ''));
            $status = $status !== '' ? $status : 'unknown';

            if (!isset($aggregate['status'][$status])) {
                $aggregate['status'][$status] = [
                    'credit' => 0.0,
                    'debit' => 0.0,
                    'count' => 0,
                ];
            }

            $aggregate['status'][$status]['count'] += 1;
            if ($isCredit) {
                $aggregate['status'][$status]['credit'] += $rowAmount;
            } else {
                $aggregate['status'][$status]['debit'] += $rowAmount;
            }

            $district = trim((string) ($row->district ?? ''));
            $district = $district !== '' ? $district : 'unknown';

            if (!isset($aggregate['district'][$district])) {
                $aggregate['district'][$district] = [
                    'credit' => 0.0,
                    'debit' => 0.0,
                    'count' => 0,
                ];
            }

            $aggregate['district'][$district]['count'] += 1;
            if ($isCredit) {
                $aggregate['district'][$district]['credit'] += $rowAmount;
            } else {
                $aggregate['district'][$district]['debit'] += $rowAmount;
            }

            $yearValue = trim((string) ($row->year ?? ''));
            $monthValue = trim((string) ($row->month ?? ''));
            $periodKey = ($yearValue !== '' ? $yearValue : 'unknown') . '-' . ($monthValue !== '' ? $monthValue : 'unknown');

            if (!isset($aggregate['period'][$periodKey])) {
                $aggregate['period'][$periodKey] = [
                    'credit' => 0.0,
                    'debit' => 0.0,
                    'count' => 0,
                ];
            }

            $aggregate['period'][$periodKey]['count'] += 1;
            if ($isCredit) {
                $aggregate['period'][$periodKey]['credit'] += $rowAmount;
            } else {
                $aggregate['period'][$periodKey]['debit'] += $rowAmount;
            }

            $cursorId = max($cursorId, (int) $row->id);
            $processedInChunk++;
        }

        unset($rows);
        gc_collect_cycles();

        $state['processed'] = (int) ($state['processed'] ?? 0) + $processedInChunk;
        $state['cursor_id'] = $cursorId;
        $state['chunk_size'] = $chunkSize;
        $state['updated_at'] = now()->toIso8601String();

        $total = max(1, (int) ($state['total'] ?? 0));
        $state['percent'] = min(99, (int) floor(($state['processed'] / $total) * 100));
        $state['message'] = "Processed {$state['processed']} / {$state['total']} rows";

        Cache::put(self::AGGREGATE_KEY, $aggregate, now()->addSeconds(self::CACHE_TTL_SECONDS));

        if ($state['processed'] >= (int) ($state['total'] ?? 0)) {
            return $this->finalize($state, $aggregate);
        }

        Cache::put(self::STATE_KEY, $state, now()->addSeconds(self::CACHE_TTL_SECONDS));

        return $state;
    }

    private function finalize(array $state, array $aggregate): array
    {
        $startAt = isset($state['started_at']) ? Carbon::parse((string) $state['started_at']) : now();

        $statusBreakdown = collect($aggregate['status'] ?? [])
            ->map(function (array $item, string $status) {
                return [
                    'status' => $status,
                    'count' => (int) ($item['count'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'debit' => (float) ($item['debit'] ?? 0),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        $topDistricts = collect($aggregate['district'] ?? [])
            ->map(function (array $item, string $district) {
                return [
                    'district' => $district,
                    'count' => (int) ($item['count'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'debit' => (float) ($item['debit'] ?? 0),
                ];
            })
            ->sortByDesc('credit')
            ->take(20)
            ->values()
            ->all();

        $periodBreakdown = collect($aggregate['period'] ?? [])
            ->map(function (array $item, string $period) {
                return [
                    'period' => $period,
                    'count' => (int) ($item['count'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'debit' => (float) ($item['debit'] ?? 0),
                ];
            })
            ->sortBy('period')
            ->values()
            ->all();

        $result = [
            'total_rows' => (int) ($aggregate['records'] ?? 0),
            'total_credit' => (float) ($aggregate['total_credit'] ?? 0),
            'total_debit' => (float) ($aggregate['total_debit'] ?? 0),
            'duration_seconds' => max(0, $startAt->diffInSeconds(now())),
            'generated_at' => now()->toDateTimeString(),
            'status_breakdown' => $statusBreakdown,
            'top_districts' => $topDistricts,
            'period_breakdown' => $periodBreakdown,
        ];

        $state['status'] = 'completed';
        $state['percent'] = 100;
        $state['processed'] = (int) ($state['total'] ?? 0);
        $state['updated_at'] = now()->toIso8601String();
        $state['completed_at'] = now()->toIso8601String();
        $state['message'] = 'Processing completed';
        $state['result'] = $result;

        Cache::put(self::RESULT_KEY, $result, now()->addSeconds(self::CACHE_TTL_SECONDS));
        Cache::put(self::STATE_KEY, $state, now()->addSeconds(self::CACHE_TTL_SECONDS));
        Cache::forget(self::AGGREGATE_KEY);
        Cache::forget(self::LOCK_KEY);

        return $state;
    }

    private function attachResultIfAvailable(array $state): array
    {
        if (($state['status'] ?? null) === 'completed' && empty($state['result'])) {
            $state['result'] = Cache::get(self::RESULT_KEY);
        }

        return $state;
    }

    private function blankAggregate(): array
    {
        return [
            'total_credit' => 0.0,
            'total_debit' => 0.0,
            'records' => 0,
            'status' => [],
            'district' => [],
            'period' => [],
        ];
    }

    private function normalizeChunkSize(?int $chunkSize): int
    {
        if ($chunkSize === null || $chunkSize === 0) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        return max(self::MIN_CHUNK_SIZE, min(self::MAX_CHUNK_SIZE, $chunkSize));
    }

    private function isStale(array $state): bool
    {
        if (($state['status'] ?? null) !== 'running') {
            return false;
        }

        $updatedAt = isset($state['updated_at']) ? Carbon::parse((string) $state['updated_at']) : null;
        if ($updatedAt === null) {
            return true;
        }

        return $updatedAt->diffInSeconds(now()) > self::STALE_AFTER_SECONDS;
    }

    private function forceReset(): void
    {
        Cache::forget(self::STATE_KEY);
        Cache::forget(self::RESULT_KEY);
        Cache::forget(self::AGGREGATE_KEY);
        Cache::forget(self::LOCK_KEY);
    }

    private function idleState(): array
    {
        return [
            'process_id' => null,
            'status' => 'idle',
            'message' => 'Ready to start',
            'started_at' => null,
            'updated_at' => now()->toIso8601String(),
            'completed_at' => null,
            'chunk_size' => self::DEFAULT_CHUNK_SIZE,
            'cursor_id' => 0,
            'processed' => 0,
            'total' => 0,
            'percent' => 0,
            'already_running' => false,
            'result' => Cache::get(self::RESULT_KEY),
        ];
    }
}
