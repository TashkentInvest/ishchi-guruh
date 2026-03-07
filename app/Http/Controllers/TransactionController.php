<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
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
        if (in_array((string) $request->query('flow', ''), ['Приход', 'Расход'], true)) {
            $where[] = 'flow = ?';
            $params[] = $request->query('flow');
        }
        if ($request->filled('type'))      { $where[] = 'type = ?';             $params[] = $request->type; }
        if ($request->filled('date_from')) { $where[] = 'date >= ?';            $params[] = $request->date_from; }
        if ($request->filled('date_to'))   { $where[] = 'date <= ?';            $params[] = $request->date_to; }

        $svodFilter = trim((string) $request->query('svod_filter', ''));
        if ($svodFilter !== '') {
            $svodCondition = $this->resolveSvodFilterCondition($svodFilter);
            if ($svodCondition !== null) {
                $where[] = '(' . $svodCondition['sql'] . ')';
                $params = array_merge($params, $svodCondition['bindings']);
            }
        }

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
                    SUM(CASE WHEN flow='Приход' THEN 1 ELSE 0 END) as credit_records,
                    SUM(CASE WHEN flow='Расход' THEN 1 ELSE 0 END) as debit_records,
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
            'svodFilters'  => $this->svodFilterOptions(),
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
        $district = trim((string) $request->query('district', ''));
        $year = trim((string) $request->query('year', ''));
        $month = trim((string) $request->query('month', ''));

        $district = $district !== '' ? $district : null;
        $year = $year !== '' ? $year : null;
        $month = $month !== '' ? $month : null;

        $filters = Cache::remember("transaction_filters_{$cacheSuffix}", self::CACHE_FILTERS, function () use ($status) {
            $query = "SELECT DISTINCT district, year, month, type FROM transactions";
            $queryParams = [];

            if ($status) {
                $query .= " WHERE status = ?";
                $queryParams[] = $status;
            }

            $query .= " ORDER BY district, year";

            $rows = DB::select($query, $queryParams);
            $districts = $years = $months = $types = [];

            foreach ($rows as $row) {
                if ($row->district) {
                    $districts[(string) $row->district] = true;
                }

                if ($row->year) {
                    $years[(string) $row->year] = true;
                }

                if ($row->month) {
                    $months[(string) $row->month] = true;
                }

                if ($row->type) {
                    $types[(string) $row->type] = true;
                }
            }

            return [
                'districts' => array_keys($districts),
                'years'     => array_keys($years),
                'months'    => array_keys($months),
                'types'     => array_keys($types),
            ];
        });

        $years = $filters['years'];
        rsort($years, SORT_NUMERIC);

        $monthOrder = [
            'Январь' => 1,
            'Февраль' => 2,
            'Март' => 3,
            'Апрель' => 4,
            'Май' => 5,
            'Июнь' => 6,
            'Июль' => 7,
            'Август' => 8,
            'Сентябрь' => 9,
            'Октябрь' => 10,
            'Ноябрь' => 11,
            'Декабрь' => 12,
        ];

        $months = $filters['months'];
        usort($months, static function (string $left, string $right) use ($monthOrder): int {
            return ($monthOrder[$left] ?? 99) <=> ($monthOrder[$right] ?? 99);
        });

        $hasExtraFilters = $district !== null || $year !== null || $month !== null;

        $buildDashboardData = function () use ($status, $district, $year, $month) {
            $thisMonthStart = now()->startOfMonth()->toDateString();
            $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
            $lastMonthEnd   = now()->subMonth()->endOfMonth()->toDateString();

            $appendScopeFilters = function (string $query, array $params = []) use ($status, $district, $year, $month): array {
                if ($status) {
                    $query .= " AND status = ?";
                    $params[] = $status;
                }

                if ($district) {
                    $query .= " AND district = ?";
                    $params[] = $district;
                }

                if ($year) {
                    $query .= " AND year = ?";
                    $params[] = $year;
                }

                if ($month) {
                    $query .= " AND month = ?";
                    $params[] = $month;
                }

                return [$query, $params];
            };

            // Overall totals (single full scan, covered by compact index)
            $overallQuery = "
                SELECT
                    SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                    SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                    COUNT(*) as total_records
                FROM transactions
                WHERE 1=1
            ";

            [$overallQuery, $overallParams] = $appendScopeFilters($overallQuery);

            $overall = DB::selectOne($overallQuery, $overallParams);

            // Distinct counters isolated to use dedicated column indexes
            $districtCountQuery = "
                SELECT COUNT(DISTINCT district) as unique_districts
                FROM transactions
                WHERE district IS NOT NULL
                  AND district <> ''
            ";

            [$districtCountQuery, $districtCountParams] = $appendScopeFilters($districtCountQuery);

            $districtCount = DB::selectOne($districtCountQuery, $districtCountParams);

            $typeCountQuery = "
                SELECT COUNT(DISTINCT type) as unique_types
                FROM transactions
                WHERE type IS NOT NULL
                  AND type <> ''
            ";

            [$typeCountQuery, $typeCountParams] = $appendScopeFilters($typeCountQuery);

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

            [$monthWindowQuery, $monthWindowParams] = $appendScopeFilters($monthWindowQuery, $monthWindowParams);

            $monthWindow = DB::selectOne($monthWindowQuery, $monthWindowParams);

            // Monthly stats — grouped by numeric year/month for index-friendly sorting
            if ($year || $month) {
                $monthlyQuery = "
                    SELECT
                        YEAR(date) as year_num,
                        MONTH(date) as month_num,
                        SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) as total_credit,
                        SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) as total_debit,
                        COUNT(*) as count
                    FROM transactions
                    WHERE 1=1
                ";

                $monthlyParams = [];
            } else {
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
            }

            [$monthlyQuery, $monthlyParams] = $appendScopeFilters($monthlyQuery, $monthlyParams);

            $monthlyQuery .= "
                GROUP BY year_num, month_num
                ORDER BY year_num DESC, month_num DESC
            ";

            if (!$year && !$month) {
                $monthlyQuery .= " LIMIT 24";
            }

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

            [$districtQuery, $districtParams] = $appendScopeFilters($districtQuery);

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

            [$typeQuery, $typeParams] = $appendScopeFilters($typeQuery);

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
        };

        if ($hasExtraFilters) {
            $viewData = $buildDashboardData();
        } else {
            $viewData = Cache::remember("dashboard_data_{$cacheSuffix}", self::CACHE_REPORT, $buildDashboardData);
        }

        $viewData['activeStatus'] = $status;
        $viewData['districts'] = $filters['districts'];
        $viewData['years'] = $years;
        $viewData['months'] = $months;
        $viewData['selectedDistrict'] = $district;
        $viewData['selectedYear'] = $year;
        $viewData['selectedMonth'] = $month;

        return view('transactions.dashboard', $viewData);
    }

    /**
     * Display summary report by districts and payment types (Свод)
     */
    public function summary(Request $request)
    {
        $status = 'gazna';
        $cacheSuffix = $this->statusCacheSuffix($status);

        $viewData = Cache::remember("summary_report_data_{$cacheSuffix}", self::CACHE_REPORT, function () use ($status) {
            $data = $this->buildSvodMatrixData($status);

            $fixedTypeColumns = [
                [
                    'bucket' => 'col_3430188',
                    'type' => '__fixed_3430188',
                    'code' => '3430188',
                    'label' => 'Жарима 5% (1 йил ичида) 3430188',
                ],
                [
                    'bucket' => 'col_3430482',
                    'type' => '__fixed_3430482',
                    'code' => '3430482',
                    'label' => 'Жарима 5% (1 йил ичида) 3430482',
                ],
                [
                    'bucket' => 'col_3430481',
                    'type' => '__fixed_3430481',
                    'code' => '3430481',
                    'label' => 'Жарима 5% (1 йил ичида) 3430481',
                ],
                [
                    'bucket' => 'col_326700',
                    'type' => '__fixed_326700',
                    'code' => '326700',
                    'label' => 'Жарима 10% (1 йилдан кейин) 326700',
                ],
                [
                    'bucket' => 'col_3430417',
                    'type' => '__fixed_3430417',
                    'code' => '3430417',
                    'label' => 'Жарима 10% (хавфсиз шаҳар)',
                ],
                [
                    'bucket' => 'col_3430189',
                    'type' => '__fixed_3430189',
                    'code' => '3430189',
                    'label' => 'Жарима 10% (1 йилдан кейин)',
                ],
                [
                    'bucket' => 'col_3430465',
                    'type' => '__fixed_3430465',
                    'code' => '3430465',
                    'label' => 'Жарима 15% (1 йил ичида)',
                ],
                [
                    'bucket' => 'col_3422292',
                    'type' => '__fixed_3422292',
                    'code' => '3422292',
                    'label' => 'Ойна тусини ўзгартириш (қорайтириш) 20%',
                ],
                [
                    'bucket' => 'col_3430135',
                    'type' => '__fixed_3430135',
                    'code' => '3430135',
                    'label' => 'Жарима 35% (автоматлаштирилган)',
                ],
            ];

            $bucketToTypeKey = [];
            foreach ($fixedTypeColumns as $column) {
                $bucketToTypeKey[$column['bucket']] = $column['type'];
            }

            $resolveBucket = function (string $type): ?string {
                $matches = [];
                preg_match('/(\d{6,7})/u', $type, $matches);
                $code = $matches[1] ?? null;

                if ($code) {
                    return match ($code) {
                        '3430188' => 'col_3430188',
                        '3430482' => 'col_3430482',
                        '3430481' => 'col_3430481',
                        '326700' => 'col_326700',
                        '3430135' => 'col_3430135',
                        '3430417' => 'col_3430417',
                        '3430465' => 'col_3430465',
                        '3430189' => 'col_3430189',
                        '3422292' => 'col_3422292',
                        default => null,
                    };
                }

                $normalized = mb_strtolower(trim($type));

                if ($normalized === '' || $normalized === '#знач!') {
                    return null;
                }

                if (str_contains($normalized, 'қорайтириш') || (str_contains($normalized, 'реклама') && str_contains($normalized, '20'))) {
                    return 'col_3422292';
                }

                if (str_contains($normalized, 'автомат') && str_contains($normalized, '35')) {
                    return 'col_3430135';
                }

                if (str_contains($normalized, 'хавфсиз') && str_contains($normalized, '10')) {
                    return 'col_3430417';
                }

                if (str_contains($normalized, '1 йил ичида') && str_contains($normalized, '15')) {
                    return 'col_3430465';
                }

                if (str_contains($normalized, '1 йилдан кейин') && str_contains($normalized, '10')) {
                    return 'col_3430189';
                }

                if (str_contains($normalized, '1 йил ичида') && str_contains($normalized, '5')) {
                    return 'col_3430188';
                }

                return null;
            };

            $bucketByType = [];
            foreach ($data['typeColumns'] as $type) {
                $bucketByType[(string) $type] = $resolveBucket((string) $type);
            }

            $orderedTypes = array_column($fixedTypeColumns, 'type');

            $orderedSummaryRows = array_map(function (array $row) use ($fixedTypeColumns, $bucketByType, $bucketToTypeKey) {
                $orderedTypeValues = [];
                foreach ($fixedTypeColumns as $column) {
                    $orderedTypeValues[$column['type']] = 0.0;
                }

                foreach ($row['types'] as $type => $value) {
                    $bucket = $bucketByType[$type] ?? null;
                    $typeKey = $bucket ? ($bucketToTypeKey[$bucket] ?? null) : null;

                    if ($typeKey === null) {
                        continue;
                    }

                    $orderedTypeValues[$typeKey] += (float) $value;
                }

                $row['types'] = $orderedTypeValues;

                return $row;
            }, $data['summaryRows']);

            $orderedTotalTypes = array_fill_keys($orderedTypes, 0.0);
            foreach ($orderedSummaryRows as $row) {
                foreach ($orderedTypes as $type) {
                    $orderedTotalTypes[$type] += (float) ($row['types'][$type] ?? 0);
                }
            }

            $typeMeta = array_map(static function (array $column): array {
                return [
                    'type' => $column['type'],
                    'code' => $column['code'],
                    'label' => $column['label'],
                    'filter' => $column['bucket'],
                ];
            }, $fixedTypeColumns);

            $data['summaryRows'] = $orderedSummaryRows;
            $data['typeColumns'] = $orderedTypes;
            $data['totals']['types'] = $orderedTotalTypes;
            $data['typeMeta'] = $typeMeta;

            return $data;
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.summary', $viewData);
    }

    /**
     * Timeline report (year/month/day columns with DB-fetched values).
     * Default scope is all statuses (Gazna + Jamgarma).
     */
    public function summaryTimeline(Request $request)
    {
        $status = $this->resolveStatus($request);
        $cacheSuffix = $this->statusCacheSuffix($status);

        $viewData = Cache::remember("summary_timeline_data_{$cacheSuffix}", self::CACHE_REPORT, function () use ($status) {
            $baseWhere = "
                FROM transactions
                WHERE date IS NOT NULL
            ";

            $baseParams = [];
            if ($status) {
                $baseWhere .= " AND status = ?";
                $baseParams[] = $status;
            }

            $periodBound = DB::selectOne(
                "
                SELECT
                    MIN(DATE(date)) as min_date,
                    MAX(DATE(date)) as max_date
                {$baseWhere}
                ",
                $baseParams
            );

            if (empty($periodBound?->min_date) || empty($periodBound?->max_date)) {
                return [
                    'yearColumns' => [],
                    'monthColumns' => [],
                    'dayColumns' => [],
                    'mainRows' => [],
                    'allocationRows' => [],
                ];
            }

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

            $period = CarbonPeriod::create(
                Carbon::parse((string) $periodBound->min_date),
                Carbon::parse((string) $periodBound->max_date)
            );

            $yearColumnsMap = [];
            $monthColumnsMap = [];
            $dayColumns = [];

            foreach ($period as $date) {
                $dayKey = $date->format('Y-m-d');
                $year = (int) $date->year;
                $monthKey = $date->format('Y-m');
                $month = (int) $date->month;

                if (!isset($yearColumnsMap[$year])) {
                    $yearColumnsMap[$year] = [
                        'key' => "Y-{$year}",
                        'label' => (string) $year,
                        'dates' => [],
                    ];
                }
                $yearColumnsMap[$year]['dates'][] = $dayKey;

                if (!isset($monthColumnsMap[$monthKey])) {
                    $monthColumnsMap[$monthKey] = [
                        'key' => "M-{$monthKey}",
                        'label' => $monthNames[$month] ?? (string) $month,
                        'dates' => [],
                    ];
                }
                $monthColumnsMap[$monthKey]['dates'][] = $dayKey;

                $dayColumns[] = [
                    'key' => "D-{$dayKey}",
                    'label' => $date->format('d.m.Y'),
                    'date' => $dayKey,
                ];
            }

            $yearColumns = array_values($yearColumnsMap);
            $monthColumns = array_values($monthColumnsMap);

            $receiptRows = DB::select(
                "
                SELECT DATE(date) as day_date, SUM(amount) / 1000000 as total
                {$baseWhere}
                  AND flow = 'Приход'
                                GROUP BY DATE(date)
                                ORDER BY DATE(date)
                ",
                $baseParams
            );

            $debitRows = DB::select(
                "
                SELECT DATE(date) as day_date, SUM(amount) / 1000000 as total
                {$baseWhere}
                  AND flow = 'Расход'
                                GROUP BY DATE(date)
                                ORDER BY DATE(date)
                ",
                $baseParams
            );

            $typeRows = DB::select(
                "
                SELECT type, MIN(id) as first_id
                {$baseWhere}
                  AND flow = 'Приход'
                  AND type IS NOT NULL
                  AND type <> ''
                GROUP BY type
                ORDER BY first_id
                ",
                $baseParams
            );

            $typeDateRows = DB::select(
                "
                SELECT type, DATE(date) as day_date, SUM(amount) / 1000000 as total
                {$baseWhere}
                  AND flow = 'Приход'
                  AND type IS NOT NULL
                  AND type <> ''
                                GROUP BY type, DATE(date)
                                ORDER BY type, DATE(date)
                ",
                $baseParams
            );

            $receiptByDay = [];
            foreach ($receiptRows as $row) {
                $receiptByDay[(string) $row->day_date] = (float) $row->total;
            }

            $debitByDay = [];
            foreach ($debitRows as $row) {
                $debitByDay[(string) $row->day_date] = (float) $row->total;
            }

            $types = array_map(static fn ($row) => (string) $row->type, $typeRows);
            $typeByDay = array_fill_keys($types, []);

            foreach ($typeDateRows as $row) {
                $type = (string) $row->type;
                if (!array_key_exists($type, $typeByDay)) {
                    $typeByDay[$type] = [];
                }

                $typeByDay[$type][(string) $row->day_date] = (float) $row->total;
            }

            $buildPeriodValues = function (array $dayMap) use ($yearColumns, $monthColumns, $dayColumns): array {
                $values = [];

                foreach ($yearColumns as $column) {
                    $sum = 0.0;
                    foreach ($column['dates'] as $date) {
                        $sum += (float) ($dayMap[$date] ?? 0);
                    }
                    $values[$column['key']] = $sum;
                }

                foreach ($monthColumns as $column) {
                    $sum = 0.0;
                    foreach ($column['dates'] as $date) {
                        $sum += (float) ($dayMap[$date] ?? 0);
                    }
                    $values[$column['key']] = $sum;
                }

                foreach ($dayColumns as $column) {
                    $values[$column['key']] = (float) ($dayMap[$column['date']] ?? 0);
                }

                return $values;
            };

            $mainRows = [];
            $mainRows[] = [
                'label' => 'ЖАМИ',
                'is_total' => true,
                'flow' => 'Приход',
                'type' => null,
                'values' => $buildPeriodValues($receiptByDay),
            ];

            foreach ($types as $index => $type) {
                $mainRows[] = [
                    'label' => ($index + 1) . '  ' . $type,
                    'is_total' => false,
                    'flow' => 'Приход',
                    'type' => $type,
                    'values' => $buildPeriodValues($typeByDay[$type] ?? []),
                ];
            }

            $distributedByDay = [];
            foreach ($dayColumns as $column) {
                $date = $column['date'];
                $distributedByDay[$date] = (float) ($receiptByDay[$date] ?? 0) - (float) ($debitByDay[$date] ?? 0);
            }

            $jamgarmaShareByDay = [];
            $budgetShareByDay = [];
            foreach ($dayColumns as $column) {
                $date = $column['date'];
                $distributed = (float) ($distributedByDay[$date] ?? 0);
                $jamgarmaShareByDay[$date] = $distributed * 0.6;
                $budgetShareByDay[$date] = $distributed * 0.4;
            }

            $allocationRows = [
                [
                    'label' => 'ЖАМИ',
                    'flow' => null,
                    'type' => null,
                    'svod_filter' => null,
                    'values' => $buildPeriodValues($distributedByDay),
                ],
                [
                    'label' => '1  Отчисление 60.0 %',
                    'flow' => 'Приход',
                    'type' => null,
                    'svod_filter' => 'col_3430188',
                    'values' => $buildPeriodValues($jamgarmaShareByDay),
                ],
                [
                    'label' => '2  Отчисление 40.0 %',
                    'flow' => 'Приход',
                    'type' => null,
                    'svod_filter' => 'col_3430482',
                    'values' => $buildPeriodValues($budgetShareByDay),
                ],
            ];

            return compact('yearColumns', 'monthColumns', 'dayColumns', 'mainRows', 'allocationRows');
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.summary_timeline', $viewData);
    }

    /**
     * Display summary report 2 - by years and months (Свод 2)
     */
    public function summary2(Request $request)
    {
        $status = $this->resolveStatus($request) ?? 'jamgarma';
        $cacheSuffix = $this->statusCacheSuffix($status);

        $viewData = Cache::remember("summary2_report_data_{$cacheSuffix}", self::CACHE_REPORT, function () use ($status) {
            return $this->buildSvodMatrixData($status);
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.summary2', $viewData);
    }

    /**
     * DB report page for Gazna (Свод 3)
     */
    public function gaznaSvod3()
    {
        $status = 'gazna';

        $viewData = Cache::remember("gazna_svod3_data_{$status}", self::CACHE_REPORT, function () use ($status) {
            return $this->buildSvodMatrixData($status);
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.gazna_svod3', $viewData);
    }

    /**
     * Jamgarma-first report with fixed table structure and DB values.
     */
    public function jamgarmaFirstSvod()
    {
        $status = 'jamgarma';

        $viewData = Cache::remember("jamgarma_first_svod_data_{$status}", self::CACHE_REPORT, function () use ($status) {
            return $this->buildJamgarmaFirstSvodData($status);
        });

        $viewData['activeStatus'] = $status;

        return view('transactions.jamgarma.jamgarma_first_svod', $viewData);
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
            $this->summaryTimeline(Request::create('/summary/timeline', 'GET', $query));
        }

        $this->gaznaSvod3();
        $this->jamgarmaFirstSvod();

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
            Cache::forget("summary_timeline_data_{$suffix}");
            Cache::forget("jamgarma_first_svod_data_{$suffix}");
            Cache::forget("dashboard_data_{$suffix}");
        }

        Cache::forget('gazna_svod3_data_gazna');

        // Legacy keys (for backward compatibility)
        Cache::forget('transaction_filters');
        Cache::forget('transaction_summary');
        Cache::forget('summary_report_data');
        Cache::forget('summary2_report_data');
        Cache::forget('summary_timeline_data');
        Cache::forget('jamgarma_first_svod_data');
        Cache::forget('jamgarma_first_svod_data_jamgarma');
        Cache::forget('dashboard_data');
    }

    private function buildJamgarmaFirstSvodData(string $status): array
    {
        $matrix = $this->buildSvodMatrixData($status);

        $bucketByType = [];
        foreach ($matrix['typeColumns'] as $type) {
            $bucketByType[$type] = $this->resolveJamgarmaFirstBucket((string) $type);
        }

        $sumByBucket = function (array $typeValues, string $bucket) use ($bucketByType): float {
            $sum = 0.0;

            foreach ($typeValues as $type => $value) {
                if (($bucketByType[$type] ?? null) === $bucket) {
                    $sum += (float) $value;
                }
            }

            return $sum;
        };

        $rows = [];
        foreach ($matrix['summaryRows'] as $row) {
            $safeCity = $sumByBucket($row['types'], 'safe_city_10');
            $automated = $sumByBucket($row['types'], 'automated_35');
            $fineFive = $sumByBucket($row['types'], 'fine_5_year');
            $fineTen = $sumByBucket($row['types'], 'fine_10_after');
            $reklama = $sumByBucket($row['types'], 'reklama_20');

            $penaltiesTotal = $safeCity + $automated + $fineFive + $fineTen;
            $total = $penaltiesTotal + $reklama;

            $rows[] = [
                'district' => (string) $row['district'],
                'total' => $total,
                'penalties_total' => $penaltiesTotal,
                'safe_city_10' => $safeCity,
                'automated_35' => $automated,
                'fine_5_year' => $fineFive,
                'fine_10_after' => $fineTen,
                'reklama_20' => $reklama,
            ];
        }

        $totals = [
            'total' => 0.0,
            'penalties_total' => 0.0,
            'safe_city_10' => 0.0,
            'automated_35' => 0.0,
            'fine_5_year' => 0.0,
            'fine_10_after' => 0.0,
            'reklama_20' => 0.0,
        ];

        foreach ($rows as $row) {
            $totals['total'] += $row['total'];
            $totals['penalties_total'] += $row['penalties_total'];
            $totals['safe_city_10'] += $row['safe_city_10'];
            $totals['automated_35'] += $row['automated_35'];
            $totals['fine_5_year'] += $row['fine_5_year'];
            $totals['fine_10_after'] += $row['fine_10_after'];
            $totals['reklama_20'] += $row['reklama_20'];
        }

        return compact('rows', 'totals');
    }

    private function extractTypeCode(string $type): ?string
    {
        if (preg_match('/(\d{6,7})/u', $type, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function resolveJamgarmaFirstBucket(string $type): ?string
    {
        $code = $this->extractTypeCode($type);
        if ($code) {
            if ($code === '3430417') {
                return 'safe_city_10';
            }

            if ($code === '3430135') {
                return 'automated_35';
            }

            if (in_array($code, ['3430188', '3430482', '3430481'], true)) {
                return 'fine_5_year';
            }

            if (in_array($code, ['3430189', '326700'], true)) {
                return 'fine_10_after';
            }

            if ($code === '3422292') {
                return 'reklama_20';
            }
        }

        $normalized = mb_strtolower(trim($type));

        if (str_contains($normalized, 'реклама') && str_contains($normalized, '20')) {
            return 'reklama_20';
        }

        if (str_contains($normalized, 'автомат') && str_contains($normalized, '35')) {
            return 'automated_35';
        }

        if (str_contains($normalized, 'хавфсиз') && str_contains($normalized, '10')) {
            return 'safe_city_10';
        }

        if (str_contains($normalized, '1 йил ичида') && str_contains($normalized, '5')) {
            return 'fine_5_year';
        }

        if (str_contains($normalized, '1 йилдан кейин') && str_contains($normalized, '10')) {
            return 'fine_10_after';
        }

        return null;
    }

    private function svodFilterOptions(): array
    {
        return [
            'col_3430188' => 'Gazna · Жарима 5% (1 йил ичида) 3430188',
            'col_3430482' => 'Gazna · Жарима 5% (1 йил ичида) 3430482',
            'col_3430481' => 'Gazna · Жарима 5% (1 йил ичида) 3430481',
            'col_326700' => 'Gazna · Жарима 10% (1 йилдан кейин) 326700',
            'col_3430417' => 'Gazna · Жарима 10% (хавфсиз шаҳар)',
            'col_3430189' => 'Gazna · Жарима 10% (1 йилдан кейин)',
            'col_3430465' => 'Gazna · Жарима 15% (1 йил ичида)',
            'col_3422292' => 'Gazna · Ойна тусини ўзгартириш 20%',
            'col_3430135' => 'Gazna · Жарима 35% (автоматлаштирилган)',
            'jam_penalties_total' => 'Jamgarma · Йўл ҳаракати жарималари (жами)',
            'jam_safe_city_10' => 'Jamgarma · Жарима 10% (хавфсиз шаҳар)',
            'jam_automated_35' => 'Jamgarma · Жарима 35% (автоматлаштирилган)',
            'jam_fine_5_year' => 'Jamgarma · Жарима 5% (1 йил ичида)',
            'jam_fine_10_after' => 'Jamgarma · Жарима 10% (1 йилдан кейин)',
            'jam_reklama_20' => 'Jamgarma · Реклама/қорайтириш 20%',
        ];
    }

    private function resolveSvodFilterCondition(string $filter): ?array
    {
        return match ($filter) {
            'col_3430188',
            'col_3430482',
            'col_3430481',
            'col_326700',
            'col_3430417',
            'col_3430189',
            'col_3430465',
            'col_3422292',
            'col_3430135' => [
                'sql' => '(' . $this->gaznaSvodBucketCaseSql() . ') = ?',
                'bindings' => [$filter],
            ],
            'jam_penalties_total' => $this->buildSvodTypeCondition([
                '3430417',
                '3430135',
                '3430188',
                '3430482',
                '3430481',
                '3430189',
                '326700',
            ], [
                ['хавфсиз', '10'],
                ['автомат', '35'],
                ['1 йил ичида', '5'],
                ['1 йилдан кейин', '10'],
            ]),
            'jam_safe_city_10' => $this->buildSvodTypeCondition(['3430417'], [
                ['хавфсиз', '10'],
            ]),
            'jam_automated_35' => $this->buildSvodTypeCondition(['3430135'], [
                ['автомат', '35'],
            ]),
            'jam_fine_5_year' => $this->buildSvodTypeCondition(['3430188', '3430482', '3430481'], [
                ['1 йил ичида', '5'],
            ]),
            'jam_fine_10_after' => $this->buildSvodTypeCondition(['3430189', '326700'], [
                ['1 йилдан кейин', '10'],
            ]),
            'jam_reklama_20' => $this->buildSvodTypeCondition(['3422292'], [
                ['қорайтириш'],
                ['реклама', '20'],
            ]),
            default => null,
        };
    }

    private function gaznaSvodBucketCaseSql(): string
    {
        return "
            CASE
                WHEN type = '3430188' OR type LIKE '%3430188%' THEN 'col_3430188'
                WHEN type = '3430482' OR type LIKE '%3430482%' THEN 'col_3430482'
                WHEN type = '3430481' OR type LIKE '%3430481%' THEN 'col_3430481'
                WHEN type = '326700' OR type LIKE '%326700%' THEN 'col_326700'
                WHEN type = '3430135' OR type LIKE '%3430135%' THEN 'col_3430135'
                WHEN type = '3430417' OR type LIKE '%3430417%' THEN 'col_3430417'
                WHEN type = '3430465' OR type LIKE '%3430465%' THEN 'col_3430465'
                WHEN type = '3430189' OR type LIKE '%3430189%' THEN 'col_3430189'
                WHEN type = '3422292' OR type LIKE '%3422292%' THEN 'col_3422292'
                WHEN (LOWER(type) LIKE '%қорайтириш%' OR (LOWER(type) LIKE '%реклама%' AND LOWER(type) LIKE '%20%')) THEN 'col_3422292'
                WHEN (LOWER(type) LIKE '%автомат%' AND LOWER(type) LIKE '%35%') THEN 'col_3430135'
                WHEN (LOWER(type) LIKE '%хавфсиз%' AND LOWER(type) LIKE '%10%') THEN 'col_3430417'
                WHEN (LOWER(type) LIKE '%1 йил ичида%' AND LOWER(type) LIKE '%15%') THEN 'col_3430465'
                WHEN (LOWER(type) LIKE '%1 йилдан кейин%' AND LOWER(type) LIKE '%10%') THEN 'col_3430189'
                WHEN (LOWER(type) LIKE '%1 йил ичида%' AND LOWER(type) LIKE '%5%') THEN 'col_3430188'
                ELSE NULL
            END
        ";
    }

    private function buildSvodTypeCondition(array $codes = [], array $likeGroups = []): array
    {
        $parts = [];
        $bindings = [];

        foreach (array_values(array_unique($codes)) as $code) {
            $parts[] = '(type = ? OR type LIKE ?)';
            $bindings[] = $code;
            $bindings[] = '%' . $code . '%';
        }

        foreach ($likeGroups as $group) {
            $tokens = array_values(array_filter(array_map(static fn ($token) => trim((string) $token), $group), static fn ($token) => $token !== ''));
            if (empty($tokens)) {
                continue;
            }

            $tokenParts = [];
            foreach ($tokens as $token) {
                $tokenParts[] = 'LOWER(type) LIKE ?';
                $bindings[] = '%' . mb_strtolower($token) . '%';
            }

            $parts[] = '(' . implode(' AND ', $tokenParts) . ')';
        }

        if (empty($parts)) {
            return [
                'sql' => '1 = 0',
                'bindings' => [],
            ];
        }

        return [
            'sql' => implode(' OR ', $parts),
            'bindings' => $bindings,
        ];
    }

    private function buildSvodMatrixData(?string $status): array
    {
        $baseWhere = "
            FROM transactions
            WHERE district IS NOT NULL
              AND district <> ''
        ";

        $baseParams = [];
        if ($status) {
            $baseWhere .= " AND status = ?";
            $baseParams[] = $status;
        }

        $typeRows = DB::select(
            "
            SELECT type, MIN(id) as first_id
            {$baseWhere}
              AND type IS NOT NULL
              AND type <> ''
            GROUP BY type
            ORDER BY first_id
            ",
            $baseParams
        );

        $typeColumns = array_map(static fn ($row) => (string) $row->type, $typeRows);

        $districtTotals = DB::select(
            "
            SELECT
                district,
                SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) / 1000000 as total_receipts,
                SUM(CASE WHEN flow='Расход' THEN amount ELSE 0 END) / 1000000 as total_debit
            {$baseWhere}
            GROUP BY district
            ORDER BY district
            ",
            $baseParams
        );

        $districtTypeTotals = DB::select(
            "
            SELECT
                district,
                type,
                SUM(CASE WHEN flow='Приход' THEN amount ELSE 0 END) / 1000000 as total
            {$baseWhere}
              AND type IS NOT NULL
              AND type <> ''
            GROUP BY district, type
            ORDER BY district
            ",
            $baseParams
        );

        $summaryRows = [];
        foreach ($districtTotals as $row) {
            $totalReceipts = (float) $row->total_receipts;
            $totalDebit = (float) $row->total_debit;
            $distributedTotal = $totalReceipts - $totalDebit;

            $summaryRows[$row->district] = [
                'district' => $row->district,
                'total_receipts' => $totalReceipts,
                'types' => array_fill_keys($typeColumns, 0.0),
                'distributed_total' => $distributedTotal,
                'jamgarma_share' => $distributedTotal * 0.6,
                'budget_share' => $distributedTotal * 0.4,
                'unallocated' => $totalDebit,
            ];
        }

        foreach ($districtTypeTotals as $row) {
            $district = (string) $row->district;
            $type = (string) $row->type;

            if (!isset($summaryRows[$district])) {
                continue;
            }

            if (!array_key_exists($type, $summaryRows[$district]['types'])) {
                $summaryRows[$district]['types'][$type] = 0.0;
            }

            $summaryRows[$district]['types'][$type] = (float) $row->total;
        }

        $summaryRows = array_values($summaryRows);

        $totals = [
            'total_receipts' => 0.0,
            'types' => array_fill_keys($typeColumns, 0.0),
            'distributed_total' => 0.0,
            'jamgarma_share' => 0.0,
            'budget_share' => 0.0,
            'unallocated' => 0.0,
        ];

        foreach ($summaryRows as $row) {
            $totals['total_receipts'] += $row['total_receipts'];
            $totals['distributed_total'] += $row['distributed_total'];
            $totals['jamgarma_share'] += $row['jamgarma_share'];
            $totals['budget_share'] += $row['budget_share'];
            $totals['unallocated'] += $row['unallocated'];

            foreach ($typeColumns as $type) {
                $totals['types'][$type] += (float) ($row['types'][$type] ?? 0);
            }
        }

        return compact('summaryRows', 'typeColumns', 'totals');
    }
}
