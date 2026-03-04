<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TransactionController extends Controller
{
    // Cache duration in seconds (10 minutes)
    const CACHE_DURATION = 600;
    /**
     * Display a listing of transactions (public homepage)
     */
    public function index(Request $request)
    {
        $query = Transaction::query();

        // Filter by district
        if ($request->filled('district')) {
            $query->where('district', $request->district);
        }

        // Filter by year
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        // Filter by month
        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Full-text search
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($q2) use ($q) {
                $q2->where('district', 'LIKE', "%{$q}%")
                   ->orWhere('type', 'LIKE', "%{$q}%")
                   ->orWhere('payment_purpose', 'LIKE', "%{$q}%")
                   ->orWhere('flow', 'LIKE', "%{$q}%");
            });
        }

        // Sorting
        $allowedSorts = ['id', 'date', 'district', 'type', 'flow', 'amount'];
        $sortField = in_array($request->sort, $allowedSorts) ? $request->sort : 'id';
        $sortDir   = $request->dir === 'asc' ? 'asc' : 'desc';

        $transactions = $query->orderBy($sortField, $sortDir)->paginate(25)->withQueryString();

        // Get unique values for filters - CACHED for performance
        $filterCacheKey = 'transaction_filters';
        $filters = Cache::remember($filterCacheKey, self::CACHE_DURATION, function () {
            return [
                'districts' => Transaction::distinct()->pluck('district')->filter()->sort()->values(),
                'years' => Transaction::distinct()->pluck('year')->filter()->sort()->values(),
                'months' => Transaction::distinct()->pluck('month')->filter()->sort()->values(),
                'types' => Transaction::distinct()->pluck('type')->filter()->sort()->values(),
            ];
        });

        // Summary statistics - CACHED
        $summaryCacheKey = 'transaction_summary';
        $summary = Cache::remember($summaryCacheKey, self::CACHE_DURATION, function () {
            return [
                'total_credit' => Transaction::sum('credit_amount'),
                'total_debit' => Transaction::sum('debit_amount'),
                'total_records' => Transaction::count(),
            ];
        });

        return view('transactions.index', [
            'transactions' => $transactions,
            'districts' => $filters['districts'],
            'years' => $filters['years'],
            'months' => $filters['months'],
            'types' => $filters['types'],
            'summary' => $summary,
        ]);
    }

    /**
     * Display dashboard with charts and statistics
     */
    public function dashboard(Request $request)
    {
        $cacheKey = 'dashboard_data';

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return view('transactions.dashboard', $cached);
        }

        // Current and last month boundaries
        $thisMonthStart  = now()->startOfMonth();
        $lastMonthStart  = now()->subMonth()->startOfMonth();
        $lastMonthEnd    = now()->subMonth()->endOfMonth();

        // Last-month summary (Приход only = credit)
        $lastMonthStats = DB::selectOne("
            SELECT
                SUM(CASE WHEN flow = 'Приход' THEN amount ELSE 0 END) as credit,
                SUM(CASE WHEN flow = 'Расход' THEN amount ELSE 0 END) as debit,
                COUNT(*) as total_records
            FROM transactions
            WHERE date >= ? AND date <= ?
        ", [$lastMonthStart, $lastMonthEnd]);

        // Current month
        $thisMonthStats = DB::selectOne("
            SELECT
                SUM(CASE WHEN flow = 'Приход' THEN amount ELSE 0 END) as credit,
                SUM(CASE WHEN flow = 'Расход' THEN amount ELSE 0 END) as debit,
                COUNT(*) as total_records
            FROM transactions
            WHERE date >= ?
        ", [$thisMonthStart]);

        // Monthly statistics - last 24 months grouped
        $monthlyStats = Transaction::select(
            'year',
            'month',
            DB::raw('SUM(CASE WHEN flow = "Приход" THEN amount ELSE 0 END) as total_credit'),
            DB::raw('SUM(CASE WHEN flow = "Расход" THEN amount ELSE 0 END) as total_debit'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderByRaw("FIELD(month, 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь') DESC")
            ->limit(24)
            ->get();

        // District statistics - top 20
        $districtStats = Transaction::select(
            'district',
            DB::raw('SUM(CASE WHEN flow = "Приход" THEN amount ELSE 0 END) as total_credit'),
            DB::raw('SUM(CASE WHEN flow = "Расход" THEN amount ELSE 0 END) as total_debit'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('district')
            ->groupBy('district')
            ->orderByDesc('total_credit')
            ->limit(20)
            ->get();

        // Type statistics
        $typeStats = Transaction::select(
            'type',
            DB::raw('SUM(CASE WHEN flow = "Приход" THEN amount ELSE 0 END) as total_credit'),
            DB::raw('SUM(CASE WHEN flow = "Расход" THEN amount ELSE 0 END) as total_debit'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('type')
            ->groupBy('type')
            ->orderByDesc('total_credit')
            ->get();

        // Overall summary
        $summary = DB::selectOne("
            SELECT
                SUM(CASE WHEN flow = 'Приход' THEN amount ELSE 0 END) as total_credit,
                SUM(CASE WHEN flow = 'Расход' THEN amount ELSE 0 END) as total_debit,
                COUNT(*) as total_records,
                COUNT(DISTINCT district) as unique_districts,
                COUNT(DISTINCT type) as unique_types
            FROM transactions
        ");

        $uzMonths = [
            1=>'Январь', 2=>'Феврал', 3=>'Март', 4=>'Апрел', 5=>'Май', 6=>'Июнь',
            7=>'Июль', 8=>'Август', 9=>'Сентябрь', 10=>'Октябрь', 11=>'Ноябрь', 12=>'Декабрь'
        ];

        $viewData = [
            'monthlyStats'   => $monthlyStats,
            'districtStats'  => $districtStats,
            'typeStats'      => $typeStats,
            'summary'        => (array) $summary,
            'lastMonthStats' => (array) $lastMonthStats,
            'thisMonthStats' => (array) $thisMonthStats,
            'lastMonthLabel' => $uzMonths[now()->subMonth()->month] . ' ' . now()->subMonth()->year,
            'thisMonthLabel' => $uzMonths[now()->month] . ' ' . now()->year,
        ];

        Cache::put($cacheKey, $viewData, self::CACHE_DURATION);

        return view('transactions.dashboard', $viewData);
    }

    /**
     * Display summary report by districts and payment types (Свод)
     */
    public function summary(Request $request)
    {
        $cacheKey = 'summary_report_data';

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return view('transactions.summary', $cached);
        }

        // Categories map to `type` column values (exact match)
        // type values: "Жарима 10% (хавфсиз шаҳар)", "Жарима 35% (автоматлаштирилган)",
        // "Жарима 5% (1 йил ичида)", "Жарима 10% (1 йилдан кейин)", "Реклама учун тўлов 20%"

        // Single batch query: district × type amounts using CASE WHEN on `type` column
        $categoryData = Transaction::select(
            'district',
            DB::raw('SUM(CASE WHEN flow = "Приход" THEN amount ELSE 0 END) / 1000000 as total'),
            DB::raw('SUM(CASE WHEN type = "Жарима 10% (хавфсиз шаҳар)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_10_safe_city'),
            DB::raw('SUM(CASE WHEN type = "Жарима 35% (автоматлаштирилган)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_35_auto'),
            DB::raw('SUM(CASE WHEN type = "Жарима 5% (1 йил ичида)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_5_within_year'),
            DB::raw('SUM(CASE WHEN type = "Жарима 10% (1 йилдан кейин)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_10_after_year'),
            DB::raw('SUM(CASE WHEN type = "Реклама учун тўлов 20%" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as ad_20')
        )
            ->whereNotNull('district')
            ->groupBy('district')
            ->orderBy('district')
            ->get()
            ->keyBy('district');

        // Build summaryData + accumulate totals
        $totals = ['total' => 0, 'fine_10_safe_city' => 0, 'fine_35_auto' => 0,
                   'fine_5_within_year' => 0, 'fine_10_after_year' => 0, 'ad_20' => 0];
        $summaryData = [];

        foreach ($categoryData as $district => $row) {
            $districtRow = [
                'district'          => $district,
                'total'             => (float) $row->total,
                'fine_10_safe_city' => (float) $row->fine_10_safe_city,
                'fine_35_auto'      => (float) $row->fine_35_auto,
                'fine_5_within_year'=> (float) $row->fine_5_within_year,
                'fine_10_after_year'=> (float) $row->fine_10_after_year,
                'ad_20'             => (float) $row->ad_20,
            ];
            foreach ($totals as $key => $_) {
                $totals[$key] += $districtRow[$key];
            }
            $summaryData[] = $districtRow;
        }

        // Balance history: last 3 months grouped by month, using type column
        $balanceHistory = Transaction::select(
            DB::raw('DATE_FORMAT(date, "%Y-%m") as month_key'),
            DB::raw('DATE_FORMAT(MAX(date), "%d.%m.%Y") as date_formatted'),
            DB::raw('SUM(CASE WHEN flow = "Приход" THEN amount ELSE 0 END) / 1000000 as total'),
            DB::raw('SUM(CASE WHEN type = "Жарима 10% (хавфсиз шаҳар)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_10_safe_city'),
            DB::raw('SUM(CASE WHEN type = "Жарима 35% (автоматлаштирилган)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_35_auto'),
            DB::raw('SUM(CASE WHEN type = "Жарима 5% (1 йил ичида)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_5_within_year'),
            DB::raw('SUM(CASE WHEN type = "Жарима 10% (1 йилдан кейин)" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as fine_10_after_year'),
            DB::raw('SUM(CASE WHEN type = "Реклама учун тўлов 20%" AND flow = "Приход" THEN amount ELSE 0 END) / 1000000 as ad_20')
        )
            ->whereDate('date', '>=', now()->subMonths(3))
            ->groupBy('month_key')
            ->orderBy('month_key', 'desc')
            ->limit(3)
            ->get();

        $viewData = [
            'summaryData'      => $summaryData,
            'totals'           => $totals,
            'balanceHistory'   => $balanceHistory,
        ];

        Cache::put($cacheKey, $viewData, self::CACHE_DURATION);

        return view('transactions.summary', $viewData);
    }

    /**
     * Display summary report 2 - by years and months (Свод 2)
     */
    public function summary2(Request $request)
    {
        $cacheKey = 'summary2_report_data';

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return view('transactions.summary2', $cached);
        }

        $years = [2023, 2024, 2025];
        $months = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        ];

        // OPTIMIZED: Single query to get all monthly data by year and flow
        $monthlyData = Transaction::select(
            DB::raw('YEAR(date) as year'),
            DB::raw('MONTH(date) as month'),
            'flow',
            DB::raw('SUM(amount) / 1000000 as total')
        )
            ->whereIn(DB::raw('YEAR(date)'), $years)
            ->whereIn('flow', ['Приход', 'Расход'])
            ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'), 'flow')
            ->get();

        // Build yearly data structure from batch query results
        $yearlyData = [];
        foreach ($years as $year) {
            $yearlyData[$year] = [
                'credit' => [],
                'debit' => [],
                'credit_total' => 0,
                'debit_total' => 0,
            ];

            foreach ($months as $monthNum => $monthName) {
                $creditRow = $monthlyData->first(function ($item) use ($year, $monthNum) {
                    return $item->year == $year && $item->month == $monthNum && $item->flow == 'Приход';
                });
                $debitRow = $monthlyData->first(function ($item) use ($year, $monthNum) {
                    return $item->year == $year && $item->month == $monthNum && $item->flow == 'Расход';
                });

                $credit = $creditRow ? $creditRow->total : 0;
                $debit = $debitRow ? $debitRow->total : 0;

                $yearlyData[$year]['credit'][$monthNum] = $credit;
                $yearlyData[$year]['debit'][$monthNum] = $debit;
                $yearlyData[$year]['credit_total'] += $credit;
                $yearlyData[$year]['debit_total'] += $debit;
            }
        }

        // OPTIMIZED: Single query for district summary
        $districtData = Transaction::select(
            'district',
            DB::raw('SUM(CASE WHEN flow = "Приход" THEN amount ELSE 0 END) / 1000000 as credit'),
            DB::raw('SUM(CASE WHEN flow = "Расход" THEN amount ELSE 0 END) / 1000000 as debit')
        )
            ->whereNotNull('district')
            ->groupBy('district')
            ->orderBy('district')
            ->get();

        $districtSummary = [];
        foreach ($districtData as $row) {
            $districtSummary[$row->district] = [
                'credit' => $row->credit,
                'debit' => $row->debit,
                'balance' => $row->credit - $row->debit,
            ];
        }

        $viewData = [
            'yearlyData' => $yearlyData,
            'districtSummary' => $districtSummary,
            'years' => $years,
            'months' => $months,
        ];

        // Cache for 10 minutes
        Cache::put($cacheKey, $viewData, self::CACHE_DURATION);

        return view('transactions.summary2', $viewData);
    }

    /**
     * Clear all report caches (call this after data import)
     */
    public function clearCache()
    {
        Cache::forget('transaction_filters');
        Cache::forget('transaction_summary');
        Cache::forget('summary_report_data');
        Cache::forget('summary2_report_data');

        return response()->json(['message' => 'Cache cleared successfully']);
    }
}
