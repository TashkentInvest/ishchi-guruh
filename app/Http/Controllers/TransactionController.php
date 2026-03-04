<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TransactionController extends Controller
{
    // Report caches: 1 hour (reports don't change mid-session)
    const CACHE_REPORT = 3600;
    // Filter/stats caches: 15 minutes
    const CACHE_FILTERS = 900;
    /**
     * Display a listing of transactions (public homepage)
     */
    public function index(Request $request)
    {
        // --- Build raw WHERE clause with bindings (Query Builder, no Eloquent hydration) ---
        $where   = [];
        $params  = [];
        $allowedSorts = ['id', 'date', 'district', 'type', 'flow', 'amount'];
        $sortField    = in_array($request->sort, $allowedSorts) ? $request->sort : 'id';
        $sortDir      = $request->dir === 'asc' ? 'ASC' : 'DESC';

        if ($request->filled('district'))  { $where[] = 'district = ?';        $params[] = $request->district; }
        if ($request->filled('year'))      { $where[] = 'year = ?';             $params[] = $request->year; }
        if ($request->filled('month'))     { $where[] = 'month = ?';            $params[] = $request->month; }
        if ($request->filled('type'))      { $where[] = 'type = ?';             $params[] = $request->type; }
        if ($request->filled('date_from')) { $where[] = 'date >= ?';            $params[] = $request->date_from; }
        if ($request->filled('date_to'))   { $where[] = 'date <= ?';            $params[] = $request->date_to; }

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            // Use FULLTEXT if available, else LIKE (FULLTEXT migration runs separately)
            $where[] = '(district LIKE ? OR type LIKE ? OR payment_purpose LIKE ?)';
            $params[] = $q; $params[] = $q; $params[] = $q;
        }

        $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // COUNT(*) via a fast covering query (no SELECT *)
        $total = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM transactions {$whereSQL}",
            $params
        )->cnt;

        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        // Fetch only needed columns — payment_purpose needed for drawer tooltip
        $rows = DB::select(
            "SELECT id, date, district, type, flow, amount, year, month, payment_purpose
             FROM transactions
             {$whereSQL}
             ORDER BY {$sortField} {$sortDir}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Manual LengthAwarePaginator (avoids Eloquent overhead on 10M rows)
        $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows, $total, $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Filter dropdown values — cached 15 min, single batched raw query
        $filters = Cache::remember('transaction_filters', self::CACHE_FILTERS, function () {
            $rows = DB::select(
                "SELECT DISTINCT district, year, month, type FROM transactions ORDER BY district, year"
            );
            $d = $y = $m = $t = [];
            foreach ($rows as $r) {
                if ($r->district) $d[$r->district] = true;
                if ($r->year)     $y[$r->year]     = true;
                if ($r->month)    $m[$r->month]    = true;
                if ($r->type)     $t[$r->type]     = true;
            }
            return [
                'districts' => array_keys($d),
                'years'     => array_keys($y),
                'months'    => array_keys($m),
                'types'     => array_keys($t),
            ];
        });

        // Global stats — single raw query, cached
        $summary = Cache::remember('transaction_summary', self::CACHE_FILTERS, function () {
            return (array) DB::selectOne(
                "SELECT
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as total_records
                 FROM transactions"
            );
        });

        return view('transactions.index', [
            'transactions' => $transactions,
            'districts'    => $filters['districts'],
            'years'        => $filters['years'],
            'months'       => $filters['months'],
            'types'        => $filters['types'],
            'summary'      => $summary,
        ]);
    }

    /**
     * Display dashboard with charts and statistics
     */
    public function dashboard(Request $request)
    {
        $viewData = Cache::remember('dashboard_data', self::CACHE_REPORT, function () {
            $thisMonthStart = now()->startOfMonth()->toDateString();
            $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
            $lastMonthEnd   = now()->subMonth()->endOfMonth()->toDateString();

            // SINGLE batch query: overall + this month + last month in one pass
            // Uses covering index (flow, amount)
            $global = DB::selectOne("
                SELECT
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END)                                        as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END)                                        as total_debit,
                    COUNT(*)                                                                                    as total_records,
                    COUNT(DISTINCT district)                                                                    as unique_districts,
                    COUNT(DISTINCT type)                                                                        as unique_types,
                    SUM(CASE WHEN flow='Приход' AND date >= ? THEN amount ELSE 0 END)                          as this_credit,
                    SUM(CASE WHEN flow='Расход' AND date >= ? THEN amount ELSE 0 END)                          as this_debit,
                    SUM(CASE WHEN date >= ? THEN 1 ELSE 0 END)                                                 as this_records,
                    SUM(CASE WHEN flow='Приход' AND date >= ? AND date <= ? THEN amount ELSE 0 END)            as last_credit,
                    SUM(CASE WHEN flow='Расход' AND date >= ? AND date <= ? THEN amount ELSE 0 END)            as last_debit,
                    SUM(CASE WHEN date >= ? AND date <= ? THEN 1 ELSE 0 END)                                   as last_records
                FROM transactions
            ", [
                $thisMonthStart, $thisMonthStart, $thisMonthStart,
                $lastMonthStart, $lastMonthEnd,
                $lastMonthStart, $lastMonthEnd,
                $lastMonthStart, $lastMonthEnd,
            ]);

            // Monthly stats — last 24 months, raw SQL, uses (date, flow, amount) covering index
            $monthlyStats = DB::select("
                SELECT year, month,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as count
                FROM transactions
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
                GROUP BY year, month
                ORDER BY year DESC,
                    FIELD(month,'Январь','Февраль','Март','Апрель','Май','Июнь',
                                'Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь') DESC
                LIMIT 24
            ");

            // District stats top 20 — uses (district, flow, amount) covering index
            $districtStats = DB::select("
                SELECT district,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as count
                FROM transactions
                WHERE district IS NOT NULL
                GROUP BY district
                ORDER BY total_credit DESC
                LIMIT 20
            ");

            // Type stats — uses (district, flow, type, amount) covering index
            $typeStats = DB::select("
                SELECT type,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as count
                FROM transactions
                WHERE type IS NOT NULL
                GROUP BY type
                ORDER BY total_credit DESC
            ");

            $uzMonths = [
                1=>'Январь', 2=>'Феврал', 3=>'Март', 4=>'Апрел', 5=>'Май', 6=>'Июнь',
                7=>'Июль', 8=>'Август', 9=>'Сентябрь', 10=>'Октябрь', 11=>'Ноябрь', 12=>'Декабрь'
            ];

            return [
                'monthlyStats'   => $monthlyStats,
                'districtStats'  => $districtStats,
                'typeStats'      => $typeStats,
                'summary'        => [
                    'total_credit'     => $global->total_credit,
                    'total_debit'      => $global->total_debit,
                    'total_records'    => $global->total_records,
                    'unique_districts' => $global->unique_districts,
                    'unique_types'     => $global->unique_types,
                ],
                'thisMonthStats' => [
                    'credit'        => $global->this_credit,
                    'debit'         => $global->this_debit,
                    'total_records' => $global->this_records,
                ],
                'lastMonthStats' => [
                    'credit'        => $global->last_credit,
                    'debit'         => $global->last_debit,
                    'total_records' => $global->last_records,
                ],
                'lastMonthLabel' => $uzMonths[now()->subMonth()->month] . ' ' . now()->subMonth()->year,
                'thisMonthLabel' => $uzMonths[now()->month] . ' ' . now()->year,
            ];
        });

        return view('transactions.dashboard', $viewData);
    }

    /**
     * Display summary report by districts and payment types (Свод)
     */
    public function summary(Request $request)
    {
        $viewData = Cache::remember('summary_report_data', self::CACHE_REPORT, function () {
            // Single raw SQL — fully covered by (district, flow, type, amount) index
            // MySQL evaluates CASE WHEN against the covering index without touching the data file
            $rows = DB::select("
                SELECT
                    district,
                    SUM(CASE WHEN type='Жарима 10% (хавфсиз шаҳар)'   AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_10_safe_city,
                    SUM(CASE WHEN type='Жарима 35% (автоматлаштирилган)' AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_35_auto,
                    SUM(CASE WHEN type='Жарима 5% (1 йил ичида)'        AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_5_within_year,
                    SUM(CASE WHEN type='Жарима 10% (1 йилдан кейин)'   AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_10_after_year,
                    SUM(CASE WHEN type='Реклама учун тўлов 20%'         AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as ad_20
                FROM transactions
                WHERE district IS NOT NULL
                GROUP BY district
                ORDER BY district
            ");

            $totals = [
                'grand_total' => 0, 'fines_total' => 0,
                'fine_10_safe_city' => 0, 'fine_35_auto' => 0,
                'fine_5_within_year' => 0, 'fine_10_after_year' => 0, 'ad_20' => 0,
            ];
            $summaryData = [];

            foreach ($rows as $row) {
                $f10  = (float) $row->fine_10_safe_city;
                $f35  = (float) $row->fine_35_auto;
                $f5   = (float) $row->fine_5_within_year;
                $f10a = (float) $row->fine_10_after_year;
                $ad20 = (float) $row->ad_20;
                $ft   = $f10 + $f35 + $f5 + $f10a;
                $gt   = $ft + $ad20;

                $summaryData[] = [
                    'district'           => $row->district,
                    'grand_total'        => $gt,
                    'fines_total'        => $ft,
                    'fine_10_safe_city'  => $f10,
                    'fine_35_auto'       => $f35,
                    'fine_5_within_year' => $f5,
                    'fine_10_after_year' => $f10a,
                    'ad_20'              => $ad20,
                ];
                $totals['grand_total']        += $gt;
                $totals['fines_total']         += $ft;
                $totals['fine_10_safe_city']   += $f10;
                $totals['fine_35_auto']         += $f35;
                $totals['fine_5_within_year']   += $f5;
                $totals['fine_10_after_year']   += $f10a;
                $totals['ad_20']                += $ad20;
            }

            // Balance history last 3 months — (date, flow, amount) covering index
            $balanceHistory = DB::select("
                SELECT
                    DATE_FORMAT(date, '%Y-%m') as month_key,
                    DATE_FORMAT(MAX(date), '%d.%m.%Y') as date_formatted,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) / 1000000 as total,
                    SUM(CASE WHEN type='Жарима 10% (хавфсиз шаҳар)'   AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_10_safe_city,
                    SUM(CASE WHEN type='Жарима 35% (автоматлаштирилган)' AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_35_auto,
                    SUM(CASE WHEN type='Жарима 5% (1 йил ичида)'        AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_5_within_year,
                    SUM(CASE WHEN type='Жарима 10% (1 йилдан кейин)'   AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as fine_10_after_year,
                    SUM(CASE WHEN type='Реклама учун тўлов 20%'         AND flow='Приход' THEN amount ELSE 0 END) / 1000000 as ad_20
                FROM transactions
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                GROUP BY month_key
                ORDER BY month_key DESC
                LIMIT 3
            ");

            return compact('summaryData', 'totals', 'balanceHistory');
        });

        return view('transactions.summary', $viewData);
    }

    /**
     * Display summary report 2 - by years and months (Свод 2)
     */
    public function summary2(Request $request)
    {
        $viewData = Cache::remember('summary2_report_data', self::CACHE_REPORT, function () {
            $months = [
                1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
                5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
                9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
            ];

            // Get distinct years — raw, no Eloquent Model instantiation
            $dbYears = DB::table('transactions')
                ->selectRaw('DISTINCT YEAR(date) as y')
                ->whereNotNull('date')
                ->orderBy('y')
                ->pluck('y')
                ->map(fn ($y) => (int) $y)
                ->toArray();

            $currentYear = (int) now()->year;
            if (!in_array($currentYear, $dbYears)) {
                $dbYears[] = $currentYear;
                sort($dbYears);
            }
            $years = $dbYears;

            // SINGLE query for all monthly data — uses (date, flow, amount) covering index
            // Build a PHP keyed lookup: ['year:month:flow'] => total
            // Eliminates the O(N×M×2) ->first() scan from the original code
            $rawMonthly = DB::select("
                SELECT
                    YEAR(date)  as yr,
                    MONTH(date) as mn,
                    flow,
                    SUM(amount) / 1000000 as total
                FROM transactions
                WHERE flow IN ('Приход', 'Расход')
                  AND YEAR(date) IN (" . implode(',', $years) . ")
                GROUP BY yr, mn, flow
            ");

            // Key the result set for O(1) lookups instead of O(N) collection scans
            $lookup = [];
            foreach ($rawMonthly as $r) {
                $lookup["{$r->yr}:{$r->mn}:{$r->flow}"] = (float) $r->total;
            }

            $yearlyData = [];
            foreach ($years as $year) {
                $yearlyData[$year] = ['credit' => [], 'debit' => [], 'credit_total' => 0, 'debit_total' => 0];
                foreach ($months as $mn => $name) {
                    $c = $lookup["{$year}:{$mn}:Приход"] ?? 0;
                    $d = $lookup["{$year}:{$mn}:Расход"] ?? 0;
                    $yearlyData[$year]['credit'][$mn]  = $c;
                    $yearlyData[$year]['debit'][$mn]   = $d;
                    $yearlyData[$year]['credit_total'] += $c;
                    $yearlyData[$year]['debit_total']  += $d;
                }
            }

            // District summary — (district, flow, amount) covering index
            $districtRows = DB::select("
                SELECT district,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) / 1000000 as credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) / 1000000 as debit
                FROM transactions
                WHERE district IS NOT NULL
                GROUP BY district
                ORDER BY district
            ");

            $districtSummary = [];
            foreach ($districtRows as $r) {
                $c = (float) $r->credit;
                $d = (float) $r->debit;
                $districtSummary[$r->district] = ['credit' => $c, 'debit' => $d, 'balance' => $c - $d];
            }

            return compact('yearlyData', 'districtSummary', 'years', 'months');
        });

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
        Cache::forget('dashboard_data');

        // JSON response for programmatic calls; redirect for form POST
        if (request()->expectsJson()) {
            return response()->json(['message' => 'Cache cleared successfully']);
        }
        return redirect()->route('admin.dashboard')
            ->with('cache_cleared', 'Kesh muvaffaqiyatli tozalandi.');
    }

    /**
     * Warm all report caches in background (call from scheduler or after import)
     */
    public function warmCache()
    {
        Cache::forget('dashboard_data');
        Cache::forget('summary_report_data');
        Cache::forget('summary2_report_data');
        Cache::forget('transaction_filters');
        Cache::forget('transaction_summary');

        // Trigger rebuilds synchronously
        $this->dashboard(request());
        $this->summary(request());
        $this->summary2(request());

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Cache warmed successfully']);
        }
        return redirect()->route('admin.dashboard')
            ->with('cache_cleared', 'Kesh qayta qurildi va issiq holatga keltirildi.');
    }
}
