@extends('layouts.app')

@section('title', 'Свод — Газна')

@push('styles')
<style>
    .report-wrap {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
        overflow: hidden;
    }

    .title-block {
        text-align: center;
        padding: 12px 14px 8px;
        background: #f6f6f6;
        border-bottom: 1px solid #dddddd;
    }

    .title-block h1,
    .title-block h2 {
        margin: 0;
        line-height: 1.2;
        color: #111;
        font-weight: 800;
    }

    .title-block h1 {
        font-size: 2.75rem;
    }

    .title-block h2 {
        margin-top: 4px;
        font-size: 2.55rem;
    }

    .title-block .title-red {
        margin-top: 6px;
        font-size: 2.55rem;
        line-height: 1;
        color: #b10000;
        font-weight: 800;
    }

    .table-wrap {
        overflow: auto;
        border-top: 1px solid #d9d9d9;
    }

    .report-table {
        width: 100%;
        min-width: 2200px;
        border-collapse: collapse;
        font-size: 2rem;
    }

    .report-table th,
    .report-table td {
        border: 1px dashed #8fa08f;
        padding: 6px 8px;
        color: #121212;
        vertical-align: middle;
    }

    .report-table thead th {
        background: #b7d2a8;
        text-align: center;
        font-weight: 700;
    }

    .report-table thead .meta-row th {
        background: #f1f1f1;
        border: 1px solid #e0e0e0;
        font-size: 2.3rem;
        font-weight: 700;
    }

    .report-table thead .meta-left {
        text-align: center;
    }

    .report-table thead .meta-right {
        text-align: right;
        font-style: italic;
    }

    .report-table tbody td:first-child {
        text-align: left;
        white-space: nowrap;
    }

    .report-table tbody td.num {
        text-align: center;
        white-space: nowrap;
    }

    .report-table .total-row td {
        font-weight: 800;
        background: #f7f7f7;
    }

    .report-table .incl-row td {
        font-style: italic;
        color: #333;
        background: #fbfbfb;
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
                        <td>Жами</td>
                        <td class="num">{{ $fmt($totals['total_receipts'] ?? 0) }}</td>
                        @forelse($typeMeta as $meta)
                            <td class="num">{{ $fmt($totals['types'][$meta['type']] ?? 0) }}</td>
                        @empty
                            <td class="num">0,0</td>
                        @endforelse
                        <td class="num">{{ $fmt($totals['distributed_total'] ?? 0) }}</td>
                        <td class="num">{{ $fmt($totals['jamgarma_share'] ?? 0) }}</td>
                        <td class="num">{{ $fmt($totals['budget_share'] ?? 0) }}</td>
                        <td class="num">{{ $fmt($totals['unallocated'] ?? 0) }}</td>
                    </tr>

                    <tr class="incl-row">
                        <td>жумладан:</td>
                        <td colspan="{{ $typeCount + 5 }}"></td>
                    </tr>

                    @foreach($summaryRows as $row)
                        <tr>
                            <td>{{ $row['district'] }}</td>
                            <td class="num">{{ $fmt($row['total_receipts']) }}</td>
                            @forelse($typeMeta as $meta)
                                <td class="num">{{ $fmt($row['types'][$meta['type']] ?? 0) }}</td>
                            @empty
                                <td class="num">0,0</td>
                            @endforelse
                            <td class="num">{{ $fmt($row['distributed_total']) }}</td>
                            <td class="num">{{ $fmt($row['jamgarma_share']) }}</td>
                            <td class="num">{{ $fmt($row['budget_share']) }}</td>
                            <td class="num">{{ $fmt($row['unallocated']) }}</td>
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
