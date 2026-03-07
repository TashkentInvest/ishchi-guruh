@extends('layouts.app')

@section('title', 'Свод — Газна')

@push('styles')
<style>
    .report-wrap {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        border: 1px solid #dfe5ea;
        overflow: hidden;
    }

    .title-block {
        text-align: center;
        padding: 16px 18px 14px;
        background: linear-gradient(120deg, #f0f9f8 0%, #eef6f9 100%);
        border-bottom: 1px solid #cfe4e6;
    }

    .title-block h1,
    .title-block h2 {
        margin: 0;
        line-height: 1.25;
        color: #015c58;
        font-weight: 800;
    }

    .title-block h1 {
        font-size: 1.35rem;
    }

    .title-block h2 {
        margin-top: 4px;
        font-size: 1.18rem;
    }

    .title-block .title-red {
        display: inline-block;
        margin-top: 10px;
        padding: 3px 12px;
        font-size: 0.78rem;
        line-height: 1.1;
        color: #018c87;
        font-weight: 800;
        letter-spacing: 0.05em;
        border-radius: 999px;
        border: 1px solid rgba(1, 140, 135, 0.25);
        background: rgba(1, 140, 135, 0.08);
    }

    .table-wrap {
        overflow: auto;
        border-top: 1px solid #d7dde1;
    }

    .report-table {
        width: 100%;
        min-width: 1800px;
        border-collapse: collapse;
        font-size: 0.84rem;
    }

    .report-table th,
    .report-table td {
        border: 1px solid #d7dde1;
        padding: 8px 10px;
        color: #27314b;
        vertical-align: middle;
    }

    .report-table thead th {
        background: #018c87;
        color: #fff;
        text-align: center;
        font-weight: 700;
        line-height: 1.35;
    }

    .report-table thead .meta-row th {
        background: #f4f7f9;
        border: 1px solid #e3e8ee;
        color: #4b5563;
        font-size: 0.79rem;
        font-weight: 700;
    }

    .report-table thead .meta-left {
        text-align: left;
        color: #334155;
    }

    .report-table thead .meta-right {
        text-align: right;
        font-style: italic;
    }

    .report-table tbody tr:nth-child(even) td {
        background: #fbfcfd;
    }

    .report-table tbody tr:hover td {
        background: #f1f7f8;
    }

    .report-table tbody td:first-child {
        text-align: left;
        white-space: nowrap;
        font-weight: 600;
        color: #111827;
        background: #fff;
    }

    .report-table tbody td.num {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .cell-link {
        color: inherit;
        text-decoration: none;
        border-bottom: 1px dashed transparent;
        transition: color .12s ease, border-color .12s ease;
    }

    .cell-link:hover {
        color: #016d69;
        border-bottom-color: #016d69;
    }

    .district-link {
        font-weight: 600;
    }

    .report-table .total-row td {
        font-weight: 800;
        background: #e8f4f3 !important;
        color: #015c58;
        border-top: 2px solid #018c87;
    }

    .report-table .incl-row td {
        font-style: italic;
        color: #6b7280;
        background: #f7f9fb !important;
    }

    .empty-note {
        text-align: center;
        padding: 24px;
        color: #6e788b;
    }

    @media print {
        .platon-header,
        .platon-aside {
            display: none !important;
        }

        .platon-main {
            margin-left: 0 !important;
        }

        .report-wrap {
            box-shadow: none;
            border: none;
        }
    }
</style>
@endpush

@section('content')
@php
    $fmt = function ($value) {
        return number_format((float) $value, 1, ',', ' ');
    };

    $monthNames = [
        1 => 'январ',
        2 => 'феврал',
        3 => 'март',
        4 => 'апрел',
        5 => 'май',
        6 => 'июн',
        7 => 'июл',
        8 => 'август',
        9 => 'сентябр',
        10 => 'октябр',
        11 => 'ноябр',
        12 => 'декабр',
    ];

    $dateLabel = now()->day . ' ' . ($monthNames[(int) now()->month] ?? now()->format('F')) . ' ' . now()->year;

    $typeMeta = $typeMeta ?? [];
    $typeCount = max(count($typeMeta), 1);

    $detailUrl = function (?string $district = null, ?string $svodFilter = null, array $extra = []) {
        $query = array_merge([
            'status' => 'gazna',
            'sort' => 'amount',
            'dir' => 'desc',
        ], $extra);

        if ($district !== null && $district !== '') {
            $query['district'] = $district;
        }

        if ($svodFilter !== null && $svodFilter !== '') {
            $query['svod_filter'] = $svodFilter;
        }

        return route('home', array_filter($query, static fn ($value) => $value !== null && $value !== ''));
    };
@endphp

<div class="report-wrap">
    <div class="title-block">
        <h1>Йўл ва транспорт инфратузилмасини ривожлантириш жамғармасига</h1>
        <h2>тушган маблағлар бўйича</h2>
        <div class="title-red">МАЪЛУМОТ</div>
    </div>

    <div class="table-wrap">
        <table class="report-table">
            <thead>
                <tr class="meta-row">
                    <th colspan="2" class="meta-left">{{ $dateLabel }}</th>
                    <th colspan="{{ $typeCount + 3 }}"></th>
                    <th class="meta-right">млн.сўм</th>
                </tr>
                <tr>
                    <th rowspan="3">Туманлар ва лойиҳалар кесимида</th>
                    <th rowspan="3">Жами тушумлар</th>
                    <th colspan="{{ $typeCount }}">Жумладан</th>
                    <th rowspan="3">Жумладан тақсимланган</th>
                    <th colspan="2">Жумладан</th>
                    <th rowspan="3">Тақсимланмаган қолдиқ</th>
                </tr>
                <tr>
                    @forelse($typeMeta as $meta)
                        <th>{{ $meta['code'] }}</th>
                    @empty
                        <th>—</th>
                    @endforelse
                    <th>3430188</th>
                    <th>3430482</th>
                </tr>
                <tr>
                    @forelse($typeMeta as $meta)
                        <th>{{ $meta['label'] }}</th>
                    @empty
                        <th>Турлар</th>
                    @endforelse
                    <th>Жамғармага<br>Отчисление 60.0 %</th>
                    <th>Бюджетга<br>Отчисление 40.0 %</th>
                </tr>
            </thead>
            <tbody>
                @if(!empty($summaryRows))
                    <tr class="total-row">
                        <td><a href="{{ $detailUrl(null, null, []) }}" class="cell-link district-link">Жами</a></td>
                        <td class="num"><a href="{{ $detailUrl(null, null, ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($totals['total_receipts'] ?? 0) }}</a></td>
                        @forelse($typeMeta as $meta)
                            <td class="num"><a href="{{ $detailUrl(null, $meta['filter'] ?? null, ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($totals['types'][$meta['type']] ?? 0) }}</a></td>
                        @empty
                            <td class="num">0,0</td>
                        @endforelse
                        <td class="num"><a href="{{ $detailUrl(null, null, []) }}" class="cell-link">{{ $fmt($totals['distributed_total'] ?? 0) }}</a></td>
                        <td class="num"><a href="{{ $detailUrl(null, 'col_3430188', ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($totals['jamgarma_share'] ?? 0) }}</a></td>
                        <td class="num"><a href="{{ $detailUrl(null, 'col_3430482', ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($totals['budget_share'] ?? 0) }}</a></td>
                        <td class="num"><a href="{{ $detailUrl(null, null, ['flow' => 'Расход']) }}" class="cell-link">{{ $fmt($totals['unallocated'] ?? 0) }}</a></td>
                    </tr>

                    <tr class="incl-row">
                        <td>жумладан:</td>
                        <td colspan="{{ $typeCount + 5 }}"></td>
                    </tr>

                    @foreach($summaryRows as $row)
                        <tr>
                            <td><a href="{{ $detailUrl($row['district'], null, []) }}" class="cell-link district-link">{{ $row['district'] }}</a></td>
                            <td class="num"><a href="{{ $detailUrl($row['district'], null, ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($row['total_receipts']) }}</a></td>
                            @forelse($typeMeta as $meta)
                                <td class="num"><a href="{{ $detailUrl($row['district'], $meta['filter'] ?? null, ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($row['types'][$meta['type']] ?? 0) }}</a></td>
                            @empty
                                <td class="num">0,0</td>
                            @endforelse
                            <td class="num"><a href="{{ $detailUrl($row['district'], null, []) }}" class="cell-link">{{ $fmt($row['distributed_total']) }}</a></td>
                            <td class="num"><a href="{{ $detailUrl($row['district'], 'col_3430188', ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($row['jamgarma_share']) }}</a></td>
                            <td class="num"><a href="{{ $detailUrl($row['district'], 'col_3430482', ['flow' => 'Приход']) }}" class="cell-link">{{ $fmt($row['budget_share']) }}</a></td>
                            <td class="num"><a href="{{ $detailUrl($row['district'], null, ['flow' => 'Расход']) }}" class="cell-link">{{ $fmt($row['unallocated']) }}</a></td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="{{ $typeCount + 6 }}" class="empty-note">Маълумот йўқ</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
