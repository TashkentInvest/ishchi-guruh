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

        $viewData = [
            'monthlyStats'   => $monthlyStats,
            'districtStats'  => $districtStats,
            'typeStats'      => $typeStats,
            'summary'        => (array) $summary,
            'lastMonthStats' => (array) $lastMonthStats,
            'thisMonthStats' => (array) $thisMonthStats,
            'lastMonthLabel' => now()->subMonth()->format('F Y'),
            'thisMonthLabel' => now()->format('F Y'),
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

        // Define payment type categories based on payment_purpose patterns
        $paymentCategories = [
            'fine_10_safe_city' => ['pattern' => '%хавфсиз шаҳар%', 'label' => 'Жарима 10% (хавфсиз шаҳар)'],
            'fine_35_auto' => ['pattern' => '%автоматлаштирилган%', 'label' => 'Жарима 35% (автоматлаштирилган)'],
            'fine_5_within_year' => ['pattern' => '%5% (1 йил ичида)%', 'label' => 'Жарима 5% (1 йил ичида)'],
            'fine_10_after_year' => ['pattern' => '%10% (1 йилдан кейин)%', 'label' => 'Жарима 10% (1 йилдан кейин)'],
            'ad_20' => ['pattern' => '%Реклама учун тўлов 20%', 'label' => 'Реклама учун тўлов 20%'],
        ];

        // Get all districts
        $districts = Transaction::distinct()
            ->whereNotNull('district')
            ->pluck('district')
            ->sort()
            ->values();

        // OPTIMIZED: Single query to get all district totals
        $districtTotals = Transaction::select('district', DB::raw('SUM(credit_amount) / 1000000 as total'))
            ->whereNotNull('district')
            ->groupBy('district')
            ->pluck('total', 'district');

        // OPTIMIZED: Single query to get all category amounts by district
        $categoryData = Transaction::select(
            'district',
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%хавфсиз шаҳар%" THEN credit_amount ELSE 0 END) / 1000000 as fine_10_safe_city'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%автоматлаштирилган%" THEN credit_amount ELSE 0 END) / 1000000 as fine_35_auto'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%5% (1 йил ичида)%" THEN credit_amount ELSE 0 END) / 1000000 as fine_5_within_year'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%10% (1 йилдан кейин)%" THEN credit_amount ELSE 0 END) / 1000000 as fine_10_after_year'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%Реклама учун тўлов 20%" THEN credit_amount ELSE 0 END) / 1000000 as ad_20')
        )
            ->whereNotNull('district')
            ->groupBy('district')
            ->get()
            ->keyBy('district');

        // Build summary data
        $summaryData = [];
        $totals = [
            'total' => 0,
            'fine_10_safe_city' => 0,
            'fine_35_auto' => 0,
            'fine_5_within_year' => 0,
            'fine_10_after_year' => 0,
            'ad_20' => 0,
        ];

        foreach ($districts as $district) {
            $catRow = $categoryData->get($district);

            $districtRow = [
                'district' => $district,
                'total' => $districtTotals->get($district, 0),
                'fine_10_safe_city' => $catRow ? $catRow->fine_10_safe_city : 0,
                'fine_35_auto' => $catRow ? $catRow->fine_35_auto : 0,
                'fine_5_within_year' => $catRow ? $catRow->fine_5_within_year : 0,
                'fine_10_after_year' => $catRow ? $catRow->fine_10_after_year : 0,
                'ad_20' => $catRow ? $catRow->ad_20 : 0,
            ];

            $totals['total'] += $districtRow['total'];
            $totals['fine_10_safe_city'] += $districtRow['fine_10_safe_city'];
            $totals['fine_35_auto'] += $districtRow['fine_35_auto'];
            $totals['fine_5_within_year'] += $districtRow['fine_5_within_year'];
            $totals['fine_10_after_year'] += $districtRow['fine_10_after_year'];
            $totals['ad_20'] += $districtRow['ad_20'];

            $summaryData[] = $districtRow;
        }

        // Get balance history (last 3 months) - single query
        $balanceHistory = Transaction::select(
            DB::raw('DATE_FORMAT(date, "%d.%m.%Y") as date_formatted'),
            DB::raw('SUM(credit_amount) / 1000000 as total'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%хавфсиз шаҳар%" THEN credit_amount ELSE 0 END) / 1000000 as fine_10_safe_city'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%автоматлаштирилган%" THEN credit_amount ELSE 0 END) / 1000000 as fine_35_auto'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%5% (1 йил ичида)%" THEN credit_amount ELSE 0 END) / 1000000 as fine_5_within_year'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%10% (1 йилдан кейин)%" THEN credit_amount ELSE 0 END) / 1000000 as fine_10_after_year'),
            DB::raw('SUM(CASE WHEN payment_purpose LIKE "%Реклама учун тўлов 20%" THEN credit_amount ELSE 0 END) / 1000000 as ad_20')
        )
            ->whereDate('date', '>=', now()->subMonths(3))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(3)
            ->get();

        $viewData = [
            'summaryData' => $summaryData,
            'totals' => $totals,
            'paymentCategories' => $paymentCategories,
            'balanceHistory' => $balanceHistory,
        ];

        // Cache for 10 minutes
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
