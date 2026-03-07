@extends('layouts.app')

@section('title', 'Бош панел')

@push('styles')
<style>
    /* ── Shared table block ── */
    .tbl-block {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .tbl-block-header {
        padding: 14px 20px;
        border-bottom: 1px solid #e8e8e8;
        font-size: 0.95rem;
        font-weight: 600;
        color: #15191e;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .tbl-block-header .sub {
        font-size: 0.75rem;
        font-weight: 400;
        color: #6e788b;
    }
    /* ── Stat cards ── */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
        position: relative;
        overflow: hidden;
    }
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0; right: 0;
        width: 4px; height: 100%;
        border-radius: 0 12px 12px 0;
    }
    .stat-card.teal::after   { background: #018c87; }
    .stat-card.blue::after   { background: #1471f0; }
    .stat-card.green::after  { background: #0bc33f; }
    .stat-card.orange::after { background: #f59e0b; }
    .stat-card.red::after    { background: #e63260; }

    .stat-card .sc-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6e788b;
        margin-bottom: 8px;
    }
    .stat-card .sc-value {
        font-size: 1.45rem;
        font-weight: 800;
        color: #15191e;
        line-height: 1.1;
    }
    .stat-card .sc-sub {
        font-size: 0.75rem;
        color: #aab0bb;
        margin-top: 6px;
    }
    .stat-card .sc-delta {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 0.72rem;
        font-weight: 600;
        margin-top: 6px;
        padding: 2px 8px;
        border-radius: 20px;
    }
    .delta-up   { background: #d4f8e8; color: #0bc33f; }
    .delta-down { background: #fde8ef; color: #e63260; }

    /* ── Two-column grid ── */
    .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    @media (max-width: 900px) {
        .two-col { grid-template-columns: 1fr; }
        .stats-row { grid-template-columns: 1fr 1fr; }
    }

    /* ── Inline table ── */
    .inline-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    .inline-table thead th {
        padding: 11px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 0.78rem;
        color: #6e788b;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 1px solid #f0f2f5;
        background: #fafafa;
        white-space: nowrap;
    }
    .inline-table thead th.num { text-align: right; }
    .inline-table tbody tr { border-bottom: 1px solid #f5f5f5; transition: background .1s; }
    .inline-table tbody tr:hover { background: #f7f9fa; }
    .inline-table tbody td { padding: 12px 16px; vertical-align: middle; }
    .inline-table tbody td.num { text-align: right; font-weight: 600; color: #27314b; }
    .inline-table tbody td.cnt { text-align: right; color: #6e788b; font-size: 0.8rem; }
    .inline-table tbody td.name { font-weight: 500; color: #15191e; }

    /* ── Progress bar ── */
    .bar-wrap { height: 5px; background: #f0f2f5; border-radius: 3px; margin-top: 6px; }
    .bar-fill  { height: 5px; background: #018c87; border-radius: 3px; }

    /* ── Month badge ── */
    .month-badge {
        display: inline-block;
        background: #f0f9f8;
        color: #018c87;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        border: 1px solid #b2e4e1;
    }

    .filters {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .filter-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 8px;
        border: none;
        background: #018c87;
        color: #fff;
        font-size: 0.82rem;
        font-weight: 600;
        line-height: 1;
        min-height: 36px;
        text-decoration: none;
    }

    .filters select.filter-btn {
        background: #fff;
        color: #333;
        border: 1px solid #dcddde;
        min-width: 170px;
        cursor: pointer;
    }

    .filter-btn svg {
        width: 16px;
        height: 16px;
    }

    .filter-btn.filter-clear {
        background: #6e788b;
        color: #fff;
    }

    .dash-link {
        color: inherit;
        text-decoration: none;
        border-bottom: 1px dashed transparent;
        transition: color .12s ease, border-color .12s ease;
    }

    .dash-link:hover {
        color: #016d69;
        border-bottom-color: #016d69;
    }

</style>
@endpush

@section('content')

@php
    $totalCredit    = $summary['total_credit'] ?? 0;
    $totalDebit     = $summary['total_debit'] ?? 0;
    $totalRecords   = $summary['total_records'] ?? 0;
    $uDistricts     = $summary['unique_districts'] ?? 0;
    $uTypes         = $summary['unique_types'] ?? 0;

    $lmCredit       = $lastMonthStats['credit'] ?? 0;
    $lmDebit        = $lastMonthStats['debit'] ?? 0;
    $lmRecords      = $lastMonthStats['total_records'] ?? 0;

    $tmCredit       = $thisMonthStats['credit'] ?? 0;
    $tmDebit        = $thisMonthStats['debit'] ?? 0;

    // Delta % vs last month
    $delta = $lmCredit > 0 ? (($tmCredit - $lmCredit) / $lmCredit * 100) : 0;
    $deltaUp = $delta >= 0;

    // Max credit for bar widths — $districtStats and $typeStats are arrays from DB::select()
    $maxDistrict = count($districtStats) ? max(array_column((array) $districtStats, 'total_credit')) : 1;
    $maxDistrict = $maxDistrict ?: 1;
    $maxType     = count($typeStats) ? max(array_column((array) $typeStats, 'total_credit')) : 1;
    $maxType     = $maxType ?: 1;

    $selectedDistrict = $selectedDistrict ?? null;
    $selectedYear = $selectedYear ?? null;
    $selectedMonth = $selectedMonth ?? null;

    $thisMonthStart = now()->startOfMonth()->toDateString();
    $thisMonthEnd = now()->endOfMonth()->toDateString();
    $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
    $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

    $detailUrl = function (array $extra = []) use ($activeStatus, $selectedDistrict, $selectedYear, $selectedMonth) {
        $query = array_merge([
            'status' => $activeStatus,
            'district' => $selectedDistrict,
            'year' => $selectedYear,
            'month' => $selectedMonth,
            'sort' => 'amount',
            'dir' => 'desc',
        ], $extra);

        return route('home', array_filter($query, static fn ($value) => $value !== null && $value !== ''));
    };
@endphp

<form id="filterForm" method="GET" action="{{ route('dashboard') }}">
    <div class="filters">
        <select name="status" class="filter-btn" onchange="document.getElementById('filterForm').submit()">
            <option value="" {{ empty($activeStatus) ? 'selected' : '' }}>Барча манбалар</option>
            <option value="jamgarma" {{ ($activeStatus ?? null) === 'jamgarma' ? 'selected' : '' }}>Jamgarma</option>
            <option value="gazna" {{ ($activeStatus ?? null) === 'gazna' ? 'selected' : '' }}>Gazna</option>
        </select>

        <select name="district" class="filter-btn" onchange="document.getElementById('filterForm').submit()">
            <option value="">Барча туманлар</option>
            @foreach(($districts ?? []) as $district)
                <option value="{{ $district }}" {{ (string) $selectedDistrict === (string) $district ? 'selected' : '' }}>
                    {{ $district }}
                </option>
            @endforeach
        </select>

        <select name="year" class="filter-btn" onchange="document.getElementById('filterForm').submit()">
            <option value="">Барча йиллар</option>
            @foreach(($years ?? []) as $year)
                <option value="{{ $year }}" {{ (string) $selectedYear === (string) $year ? 'selected' : '' }}>
                    {{ $year }}
                </option>
            @endforeach
        </select>

        <select name="month" class="filter-btn" onchange="document.getElementById('filterForm').submit()">
            <option value="">Барча ойлар</option>
            @foreach(($months ?? []) as $month)
                <option value="{{ $month }}" {{ (string) $selectedMonth === (string) $month ? 'selected' : '' }}>
                    {{ $month }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="filter-btn">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M6.768 4.066A2.5 2.5 0 113.232 7.6a2.5 2.5 0 013.536-3.535M16.667 5.833H7.5M16.768 12.399a2.5 2.5 0 11-3.536 3.535 2.5 2.5 0 013.536-3.535M3.333 14.167H12.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            Филтр
        </button>

        <a href="{{ route('dashboard') }}" class="filter-btn filter-clear">Тозалаш</a>
    </div>
</form>

{{-- ── Top stat cards ── --}}
<div class="stats-row">
    <div class="stat-card teal">
        <div class="sc-label">Жами Кредит (Приход)</div>
        <div class="sc-value"><a href="{{ $detailUrl(['flow' => 'Приход']) }}" class="dash-link">{{ number_format($totalCredit / 1000000, 1, '.', ' ') }} млн</a></div>
        <div class="sc-sub">сўм</div>
    </div>
    <div class="stat-card blue">
        <div class="sc-label">Жами Дебет (Расход)</div>
        <div class="sc-value"><a href="{{ $detailUrl(['flow' => 'Расход']) }}" class="dash-link">{{ number_format($totalDebit / 1000000, 1, '.', ' ') }} млн</a></div>
        <div class="sc-sub">сўм</div>
    </div>
    <div class="stat-card green">
        <div class="sc-label">Жами Йозувлар</div>
        <div class="sc-value"><a href="{{ $detailUrl() }}" class="dash-link">{{ number_format($totalRecords) }}</a></div>
        <div class="sc-sub">{{ $uDistricts }} туман · {{ $uTypes }} тур</div>
    </div>
    <div class="stat-card orange">
        <div class="sc-label">{{ $lastMonthLabel }} (Ўтган ой)</div>
        <div class="sc-value"><a href="{{ $detailUrl(['flow' => 'Приход', 'date_from' => $lastMonthStart, 'date_to' => $lastMonthEnd]) }}" class="dash-link">{{ number_format($lmCredit / 1000000, 1, '.', ' ') }} млн</a></div>
        <div class="sc-sub">{{ number_format($lmRecords) }} та йозув</div>
    </div>
    <div class="stat-card {{ $deltaUp ? 'teal' : 'red' }}">
        <div class="sc-label">{{ $thisMonthLabel }} (Жорий ой)</div>
        <div class="sc-value"><a href="{{ $detailUrl(['flow' => 'Приход', 'date_from' => $thisMonthStart, 'date_to' => $thisMonthEnd]) }}" class="dash-link">{{ number_format($tmCredit / 1000000, 1, '.', ' ') }} млн</a></div>
        <span class="sc-delta {{ $deltaUp ? 'delta-up' : 'delta-down' }}">
            {{ $deltaUp ? '▲' : '▼' }} {{ number_format(abs($delta), 1) }}%
            ўтган ойга нисбатан
        </span>
    </div>
</div>

{{-- ── Two-column: Monthly + Districts ── --}}
<div class="two-col">
    {{-- Monthly Table --}}
    <div class="tbl-block">
        <div class="tbl-block-header">
            Ойлик статистика
            <span class="sub">Охирги 24 ой</span>
        </div>
        <div style="overflow-x:auto; max-height: 400px; overflow-y:auto;">
            <table class="inline-table">
                <thead>
                    <tr>
                        <th>Йил</th>
                        <th>Ой</th>
                        <th class="num">Кредит (сўм)</th>
                        <th class="num">Дебет (сўм)</th>
                        <th class="num">Сони</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthlyStats as $stat)
                        @php
                            $monthStart = sprintf('%04d-%02d-01', (int) $stat->year, (int) $stat->month_num);
                            $monthEnd = \Carbon\Carbon::parse($monthStart)->endOfMonth()->toDateString();
                        @endphp
                        <tr>
                            <td><a href="{{ $detailUrl(['date_from' => $monthStart, 'date_to' => $monthEnd]) }}" class="dash-link"><span class="month-badge">{{ $stat->year }}</span></a></td>
                            <td class="name"><a href="{{ $detailUrl(['date_from' => $monthStart, 'date_to' => $monthEnd]) }}" class="dash-link">{{ $stat->month }}</a></td>
                            <td class="num"><a href="{{ $detailUrl(['date_from' => $monthStart, 'date_to' => $monthEnd, 'flow' => 'Приход']) }}" class="dash-link">{{ number_format($stat->total_credit / 1000000, 2, '.', ' ') }} млн</a></td>
                            <td class="num"><a href="{{ $detailUrl(['date_from' => $monthStart, 'date_to' => $monthEnd, 'flow' => 'Расход']) }}" class="dash-link">{{ number_format($stat->total_debit / 1000000, 2, '.', ' ') }} млн</a></td>
                            <td class="cnt"><a href="{{ $detailUrl(['date_from' => $monthStart, 'date_to' => $monthEnd]) }}" class="dash-link">{{ number_format($stat->count) }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center;padding:30px;color:#aab0bb;">Маълумот йўқ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- District Table --}}
    <div class="tbl-block">
        <div class="tbl-block-header">
            Туманлар бўйича (Top 20)
            <span class="sub">Кредит бўйича тартибланган</span>
        </div>
        <div style="overflow-x:auto; max-height: 400px; overflow-y:auto;">
            <table class="inline-table">
                <thead>
                    <tr>
                        <th>Туман</th>
                        <th class="num">Кредит</th>
                        <th class="num">Сони</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($districtStats as $stat)
                        @php $pct = $maxDistrict > 0 ? ($stat->total_credit / $maxDistrict * 100) : 0; @endphp
                        <tr>
                            <td class="name">
                                <a href="{{ $detailUrl(['district' => $stat->district]) }}" class="dash-link">{{ $stat->district }}</a>
                                <div class="bar-wrap"><div class="bar-fill" style="width:{{ $pct }}%"></div></div>
                            </td>
                            <td class="num"><a href="{{ $detailUrl(['district' => $stat->district, 'flow' => 'Приход']) }}" class="dash-link">{{ number_format($stat->total_credit / 1000000, 1, '.', ' ') }} млн</a></td>
                            <td class="cnt"><a href="{{ $detailUrl(['district' => $stat->district]) }}" class="dash-link">{{ number_format($stat->count) }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="text-align:center;padding:30px;color:#aab0bb;">Маълумот йўқ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Payment types ── --}}
<div class="tbl-block">
    <div class="tbl-block-header">
        Тўлов турлари бўйича
        <span class="sub">Барча турлар</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="inline-table">
            <thead>
                <tr>
                    <th>Тури</th>
                    <th class="num">Кредит (сўм)</th>
                    <th class="num">Дебет (сўм)</th>
                    <th class="num">Сони</th>
                    <th style="width:180px;">Улуш</th>
                </tr>
            </thead>
            <tbody>
                @forelse($typeStats as $stat)
                    @php $pct = $maxType > 0 ? ($stat->total_credit / $maxType * 100) : 0; @endphp
                    <tr>
                        <td class="name"><a href="{{ $detailUrl(['type' => $stat->type]) }}" class="dash-link">{{ $stat->type ?? '—' }}</a></td>
                        <td class="num"><a href="{{ $detailUrl(['type' => $stat->type, 'flow' => 'Приход']) }}" class="dash-link">{{ number_format($stat->total_credit / 1000000, 2, '.', ' ') }} млн</a></td>
                        <td class="num"><a href="{{ $detailUrl(['type' => $stat->type, 'flow' => 'Расход']) }}" class="dash-link">{{ number_format($stat->total_debit / 1000000, 2, '.', ' ') }} млн</a></td>
                        <td class="cnt"><a href="{{ $detailUrl(['type' => $stat->type]) }}" class="dash-link">{{ number_format($stat->count) }}</a></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="bar-wrap" style="flex:1;">
                                    <div class="bar-fill" style="width:{{ $pct }}%; background:#1471f0;"></div>
                                </div>
                                <span style="font-size:0.72rem;color:#6e788b;white-space:nowrap;">{{ number_format($pct, 0) }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:#aab0bb;">Маълумот йўқ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
