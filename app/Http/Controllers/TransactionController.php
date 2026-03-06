<?php

namespace App\Http\Controllers;

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
        $status = $this->resolveStatus($request);
        $cacheSuffix = $this->statusCacheSuffix($status);

        // --- Build raw WHERE clause with bindings (Query Builder, no Eloquent hydration) ---
        $where   = [];
        $params  = [];
        $allowedSorts = ['id', 'date', 'district', 'type', 'flow', 'amount', 'status'];
        $sortField    = in_array($request->sort, $allowedSorts) ? $request->sort : 'id';
        $sortDir      = $request->dir === 'asc' ? 'ASC' : 'DESC';

        if ($status) { $where[] = 'status = ?';            $params[] = $status; }

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
            "SELECT id, date, district, type, flow, amount, year, month, payment_purpose, status
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
        $filters = Cache::remember("transaction_filters_{$cacheSuffix}", self::CACHE_FILTERS, function () use ($status) {
            $query = "SELECT DISTINCT district, year, month, type FROM transactions";
            $queryParams = [];

            if ($status) {
                $query .= " WHERE status = ?";
                $queryParams[] = $status;
            }

            $query .= " ORDER BY district, year";

            $rows = DB::select(
                $query,
                $queryParams
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
        $summary = Cache::remember("transaction_summary_{$cacheSuffix}", self::CACHE_FILTERS, function () use ($status) {
            $query = "SELECT
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as total_records
                 FROM transactions";
            $queryParams = [];

            if ($status) {
                $query .= " WHERE status = ?";
                $queryParams[] = $status;
            }

            return (array) DB::selectOne(
                $query,
                $queryParams
            );
        });

        return view('transactions.index', [
            'transactions' => $transactions,
            'districts'    => $filters['districts'],
            'years'        => $filters['years'],
            'months'       => $filters['months'],
            'types'        => $filters['types'],
            'summary'      => $summary,
            'activeStatus' => $status,
        ]);
    }

    /**
     * Display dashboard with charts and statistics
     */
    public function dashboard(Request $request)
    {
        $status = $this->resolveStatus($request);
        $cacheSuffix = $this->statusCacheSuffix($status);

        $viewData = Cache::remember("dashboard_data_{$cacheSuffix}", self::CACHE_REPORT, function () use ($status) {
            $thisMonthStart = now()->startOfMonth()->toDateString();
            $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
            $lastMonthEnd   = now()->subMonth()->endOfMonth()->toDateString();

            // Overall totals (single full scan, covered by compact index)
            $overallQuery = "
                SELECT
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as total_records
                FROM transactions
            ";

            $overallParams = [];
            if ($status) {
                $overallQuery .= " WHERE status = ?";
                $overallParams[] = $status;
            }

            $overall = DB::selectOne($overallQuery, $overallParams);

            // Distinct counters isolated to use dedicated column indexes
            $districtCountQuery = "
                SELECT COUNT(DISTINCT district) as unique_districts
                FROM transactions
                WHERE district IS NOT NULL
                  AND district <> ''
            ";

            $districtCountParams = [];
            if ($status) {
                $districtCountQuery .= " AND status = ?";
                $districtCountParams[] = $status;
            }

            $districtCount = DB::selectOne($districtCountQuery, $districtCountParams);

            $typeCountQuery = "
                SELECT COUNT(DISTINCT type) as unique_types
                FROM transactions
                WHERE type IS NOT NULL
                  AND type <> ''
            ";

            $typeCountParams = [];
            if ($status) {
                $typeCountQuery .= " AND status = ?";
                $typeCountParams[] = $status;
            }

            $typeCount = DB::selectOne($typeCountQuery, $typeCountParams);

            // This + last month in a narrow date window (last month start -> now)
            $monthWindowQuery = "
                SELECT
                    SUM(CASE WHEN flow='Приход' AND date >= ? THEN amount ELSE 0 END) as this_credit,
                    SUM(CASE WHEN flow='Расход' AND date >= ? THEN amount ELSE 0 END) as this_debit,
                    SUM(CASE WHEN date >= ? THEN 1 ELSE 0 END) as this_records,
                    SUM(CASE WHEN flow='Приход' AND date <= ? THEN amount ELSE 0 END) as last_credit,
                    SUM(CASE WHEN flow='Расход' AND date <= ? THEN amount ELSE 0 END) as last_debit,
                    SUM(CASE WHEN date <= ? THEN 1 ELSE 0 END) as last_records
                FROM transactions
                WHERE date >= ?
            ";

            $monthWindowParams = [
                $thisMonthStart,
                $thisMonthStart,
                $thisMonthStart,
                $lastMonthEnd,
                $lastMonthEnd,
                $lastMonthEnd,
                $lastMonthStart,
            ];

            if ($status) {
                $monthWindowQuery .= " AND status = ?";
                $monthWindowParams[] = $status;
            }

            $monthWindow = DB::selectOne($monthWindowQuery, $monthWindowParams);

            // Monthly stats — grouped by numeric year/month for index-friendly sorting
            $monthlyStart = now()->subMonths(24)->startOfMonth()->toDateString();

            $monthlyQuery = "
                SELECT
                    YEAR(date) as year_num,
                    MONTH(date) as month_num,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as count
                FROM transactions
                WHERE date >= ?
            ";

            $monthlyParams = [$monthlyStart];
            if ($status) {
                $monthlyQuery .= " AND status = ?";
                $monthlyParams[] = $status;
            }

            $monthlyQuery .= "
                GROUP BY year_num, month_num
                ORDER BY year_num DESC, month_num DESC
                LIMIT 24
            ";

            $monthlyRaw = DB::select($monthlyQuery, $monthlyParams);

            $monthNames = [
                1 => 'Январь',
                2 => 'Февраль',
                3 => 'Март',
                4 => 'Апрель',
                5 => 'Май',
                6 => 'Июнь',
                7 => 'Июль',
                8 => 'Август',
                9 => 'Сентябрь',
                10 => 'Октябрь',
                11 => 'Ноябрь',
                12 => 'Декабрь',
            ];

            $monthlyStats = array_map(function ($row) use ($monthNames) {
                $row->year = (int) $row->year_num;
                $row->month = $monthNames[(int) $row->month_num] ?? (string) $row->month_num;

                return $row;
            }, $monthlyRaw);

            // District stats top 20 — uses (district, flow, amount) covering index
            $districtQuery = "
                SELECT district,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as count
                FROM transactions
                WHERE district IS NOT NULL
                  AND district <> ''
            ";

            $districtParams = [];
            if ($status) {
                $districtQuery .= " AND status = ?";
                $districtParams[] = $status;
            }

            $districtQuery .= "
                GROUP BY district
                ORDER BY total_credit DESC
                LIMIT 20
            ";

            $districtStats = DB::select($districtQuery, $districtParams);

            // Type stats — uses (district, flow, type, amount) covering index
            $typeQuery = "
                SELECT type,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as count
                FROM transactions
                WHERE type IS NOT NULL
                  AND type <> ''
            ";

            $typeParams = [];
            if ($status) {
                $typeQuery .= " AND status = ?";
                $typeParams[] = $status;
            }

            $typeQuery .= "
                GROUP BY type
                ORDER BY total_credit DESC
            ";

            $typeStats = DB::select($typeQuery, $typeParams);

            $uzMonths = [
                1=>'Январь', 2=>'Февраль', 3=>'Март', 4=>'Апрель', 5=>'Май', 6=>'Июнь',
                7=>'Июль', 8=>'Август', 9=>'Сентябрь', 10=>'Октябрь', 11=>'Ноябрь', 12=>'Декабрь'
            ];

            return [
                'monthlyStats'   => $monthlyStats,
                'districtStats'  => $districtStats,
                'typeStats'      => $typeStats,
                'summary'        => [
                    'total_credit'     => (float) ($overall->total_credit ?? 0),
                    'total_debit'      => (float) ($overall->total_debit ?? 0),
                    'total_records'    => (int) ($overall->total_records ?? 0),
                    'unique_districts' => (int) ($districtCount->unique_districts ?? 0),
                    'unique_types'     => (int) ($typeCount->unique_types ?? 0),
                ],
                'thisMonthStats' => [
                    'credit'        => (float) ($monthWindow->this_credit ?? 0),
                    'debit'         => (float) ($monthWindow->this_debit ?? 0),
                    'total_records' => (int) ($monthWindow->this_records ?? 0),
                ],
                'lastMonthStats' => [
                    'credit'        => (float) ($monthWindow->last_credit ?? 0),
                    'debit'         => (float) ($monthWindow->last_debit ?? 0),
                    'total_records' => (int) ($monthWindow->last_records ?? 0),
                ],
                'lastMonthLabel' => $uzMonths[now()->subMonth()->month] . ' ' . now()->subMonth()->year,
                'thisMonthLabel' => $uzMonths[now()->month] . ' ' . now()->year,
            ];
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.dashboard', $viewData);
    }

    /**
     * Display summary report by districts and payment types (Свод)
     */
    public function summary(Request $request)
    {
        $status = $this->resolveStatus($request);
        $cacheSuffix = $this->statusCacheSuffix($status);

        $viewData = Cache::remember("summary_report_data_{$cacheSuffix}", self::CACHE_REPORT, function () use ($status) {
            $baseWhere = "
                FROM transactions
                WHERE flow = 'Приход'
                  AND district IS NOT NULL
                  AND district <> ''
                  AND type IS NOT NULL
                  AND type <> ''
            ";

            $baseParams = [];
            if ($status) {
                $baseWhere .= " AND status = ?";
                $baseParams[] = $status;
            }

            // Dynamic type columns from DB (no hardcoding)
            $typeRows = DB::select(
                "
                SELECT type, SUM(amount) / 1000000 as total
                {$baseWhere}
                GROUP BY type
                HAVING SUM(amount) > 0
                ORDER BY total DESC
                " ,
                $baseParams
            );

            $typeColumns = array_map(static fn($row) => $row->type, $typeRows);

            $totals = [
                'grand_total' => 0,
                'types' => array_fill_keys($typeColumns, 0.0),
            ];

            foreach ($typeRows as $row) {
                $value = (float) $row->total;
                $totals['types'][$row->type] = $value;
                $totals['grand_total'] += $value;
            }

            // District x type matrix
            $rows = DB::select(
                "
                SELECT district, type, SUM(amount) / 1000000 as total
                {$baseWhere}
                GROUP BY district, type
                ORDER BY district, type
                ",
                $baseParams
            );

            $summaryData = [];
            foreach ($rows as $row) {
                $district = $row->district;
                $type = $row->type;
                $value = (float) $row->total;

                if (!array_key_exists($district, $summaryData)) {
                    $summaryData[$district] = [
                        'district' => $district,
                        'grand_total' => 0,
                        'types' => array_fill_keys($typeColumns, 0.0),
                    ];
                }

                $summaryData[$district]['types'][$type] = $value;
                $summaryData[$district]['grand_total'] += $value;
            }

            $summaryData = array_values($summaryData);

            return compact('summaryData', 'totals', 'typeColumns');
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.summary', $viewData);
    }

    /**
     * Display summary report 2 - by years and months (Свод 2)
     */
    public function summary2(Request $request)
    {
        $status = $this->resolveStatus($request);
        $cacheSuffix = $this->statusCacheSuffix($status);

        $viewData = Cache::remember("summary2_report_data_{$cacheSuffix}", self::CACHE_REPORT, function () use ($status) {
            $months = [
                1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
                5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
                9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
            ];

            // Get distinct years — raw, no Eloquent Model instantiation
            $dbYearsQuery = DB::table('transactions')
                ->selectRaw('DISTINCT YEAR(date) as y')
                ->whereNotNull('date');

            if ($status) {
                $dbYearsQuery->where('status', $status);
            }

            $dbYears = $dbYearsQuery
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

            if (empty($years)) {
                $years = [$currentYear];
            }

            $safeYears = array_map(fn ($year) => (int) $year, $years);

            // SINGLE query for all monthly data — uses (date, flow, amount) covering index
            // Build a PHP keyed lookup: ['year:month:flow'] => total
            // Eliminates the O(N×M×2) ->first() scan from the original code
            $monthlyQuery = "
                SELECT
                    YEAR(date)  as yr,
                    MONTH(date) as mn,
                    flow,
                    SUM(amount) / 1000000 as total
                FROM transactions
                WHERE flow IN ('Приход', 'Расход')
                  AND YEAR(date) IN (" . implode(',', $safeYears) . ")
            ";

            $monthlyParams = [];
            if ($status) {
                $monthlyQuery .= " AND status = ?";
                $monthlyParams[] = $status;
            }

            $monthlyQuery .= " GROUP BY yr, mn, flow";

            $rawMonthly = DB::select($monthlyQuery, $monthlyParams);

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
            $districtQuery = "
                SELECT district,
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) / 1000000 as credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) / 1000000 as debit
                FROM transactions
                WHERE district IS NOT NULL
            ";

            $districtParams = [];
            if ($status) {
                $districtQuery .= " AND status = ?";
                $districtParams[] = $status;
            }

            $districtQuery .= "
                GROUP BY district
                ORDER BY district
            ";

            $districtRows = DB::select($districtQuery, $districtParams);

            $districtSummary = [];
            foreach ($districtRows as $r) {
                $c = (float) $r->credit;
                $d = (float) $r->debit;
                $districtSummary[$r->district] = ['credit' => $c, 'debit' => $d, 'balance' => $c - $d];
            }

            return compact('yearlyData', 'districtSummary', 'years', 'months');
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.summary2', $viewData);
    }

    /**
     * CSV report page for GAZNA_SVOD2.csv
     */
    public function gaznaSvod2()
    {
        $data = $this->loadCsvRows('GAZNA_SVOD2.csv', 2000);

        return view('transactions.gazna_svod2', [
            'rows' => $data['rows'],
            'maxColumns' => $data['max_columns'],
            'isTruncated' => $data['is_truncated'],
            'activeStatus' => 'gazna',
        ]);
    }

    /**
     * CSV report page for FORMULAS_GAZNA.csv (Свод 3)
     */
    public function gaznaSvod3()
    {
        $data = $this->loadCsvRows('FORMULAS_GAZNA.csv', 3000);

        return view('transactions.gazna_svod3', [
            'rows' => $data['rows'],
            'maxColumns' => $data['max_columns'],
            'isTruncated' => $data['is_truncated'],
            'activeStatus' => 'gazna',
        ]);
    }

    /**
     * Jamgarma page in Gazna SVOD2-like report flow.
     */
    public function jamgarmaYol(Request $request)
    {
        $request->merge(['status' => 'jamgarma']);

        return $this->summary($request);
    }

    /**
     * Clear all report caches (call this after data import)
     */
    public function clearCache()
    {
        $this->flushReportCaches();

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
        $this->flushReportCaches();

        // Trigger rebuilds synchronously for all statuses
        $statuses = [null, 'jamgarma', 'gazna'];
        foreach ($statuses as $status) {
            $query = $status ? ['status' => $status] : [];

            $this->index(Request::create('/home', 'GET', $query));
            $this->dashboard(Request::create('/dashboard', 'GET', $query));
            $this->summary(Request::create('/summary', 'GET', $query));
            $this->summary2(Request::create('/summary2', 'GET', $query));
        }

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Cache warmed successfully']);
        }
        return redirect()->route('admin.dashboard')
            ->with('cache_cleared', 'Kesh qayta qurildi va issiq holatga keltirildi.');
    }

    private function resolveStatus(Request $request): ?string
    {
        $status = mb_strtolower((string) $request->query('status', ''));

        return in_array($status, ['jamgarma', 'gazna'], true) ? $status : null;
    }

    private function statusCacheSuffix(?string $status): string
    {
        return $status ?: 'all';
    }

    private function flushReportCaches(): void
    {
        // New status-aware keys
        foreach (['all', 'jamgarma', 'gazna'] as $suffix) {
            Cache::forget("transaction_filters_{$suffix}");
            Cache::forget("transaction_summary_{$suffix}");
            Cache::forget("summary_report_data_{$suffix}");
            Cache::forget("summary2_report_data_{$suffix}");
            Cache::forget("dashboard_data_{$suffix}");
        }

        // Legacy keys (for backward compatibility)
        Cache::forget('transaction_filters');
        Cache::forget('transaction_summary');
        Cache::forget('summary_report_data');
        Cache::forget('summary2_report_data');
        Cache::forget('dashboard_data');
    }

    private function loadCsvRows(string $fileName, int $maxRows = 2000): array
    {
        $path = storage_path('app/public/detalization/' . $fileName);

        if (!is_file($path)) {
            return ['rows' => [], 'max_columns' => 0, 'is_truncated' => false];
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['rows' => [], 'max_columns' => 0, 'is_truncated' => false];
        }

        $rows = [];
        $maxColumns = 0;
        $isTruncated = false;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (!is_array($row)) {
                continue;
            }

            $cleaned = array_map(function ($cell) {
                return $this->normalizeCsvCell((string) $cell);
            }, $row);

            while (!empty($cleaned) && end($cleaned) === '') {
                array_pop($cleaned);
            }

            if (empty($cleaned)) {
                continue;
            }

            $maxColumns = max($maxColumns, count($cleaned));
            $rows[] = $cleaned;

            if (count($rows) >= $maxRows) {
                $isTruncated = true;
                break;
            }
        }

        fclose($handle);

        if ($maxColumns > 0) {
            foreach ($rows as &$row) {
                if (count($row) < $maxColumns) {
                    $row = array_pad($row, $maxColumns, '');
                }
            }
            unset($row);
        }

        return [
            'rows' => $rows,
            'max_columns' => $maxColumns,
            'is_truncated' => $isTruncated,
        ];
    }

    private function normalizeCsvCell(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value;
    }
}
