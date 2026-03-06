@extends('layouts.app')

@section('title', 'Свод 2 - Йиллик ҳисобот')

@push('styles')
<style>
    .report-container {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
        padding: 20px;
    }

    .report-header {
        text-align: center;
        margin-bottom: 16px;
        padding: 18px 16px 14px;
        background: #f1f1f1;
        border: 1px solid #dddddd;
        border-radius: 8px;
    }

    .report-header h1 {
        font-size: 2.9rem;
        font-weight: 700;
        margin: 0 0 4px;
        color: #111;
        line-height: 1.1;
    }

    .report-header h2 {
        font-size: 2.9rem;
        font-weight: 700;
        margin: 0 0 2px;
        color: #111;
        line-height: 1.1;
    }

    .report-header .info {
        font-size: 2rem;
        font-weight: 800;
        color: #b10000;
        line-height: 1;
    }

    .table-wrap {
        overflow: auto;
        border: 1px solid #d9d9d9;
    }

    .report-table {
        width: 100%;
        min-width: 1600px;
        border-collapse: collapse;
        font-size: 1.65rem;
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
        font-size: 2.4rem;
        font-weight: 700;
    }

    .report-table thead .meta-left {
        text-align: left;
    }

    .report-table thead .meta-right {
        text-align: right;
        font-style: italic;
    }

    .report-table tbody td:first-child {
        font-weight: 500;
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

    .print-row {
        margin-top: 14px;
        text-align: right;
    }

    .print-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: #018c87;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
    }

    .empty-note {
        text-align: center;
        padding: 24px;
        color: #6e788b;
    }

    @media print {
        .platon-header,
        .platon-aside,
        .print-row {
            display: none !important;
        }

        .platon-main {
            margin-left: 0 !important;
        }

        .report-container {
            box-shadow: none;
            border: none;
            padding: 0;
        }
    }
</style>
@endpush

@section('content')
@php
    $statusLabel = ($activeStatus ?? null) === 'gazna'
        ? 'GAZNA'
        : (($activeStatus ?? null) === 'jamgarma' ? 'JAMGARMA' : 'БАРЧАСИ');

    $fmt = function ($value) {
        return number_format((float) $value, 1, ',', ' ');
    };

    $extractCode = function (string $type): string {
        if (preg_match('/(\d{6,7})/u', $type, $m)) {
            return $m[1];
        }

        return '—';
    };

    $typeCount = max(count($typeColumns ?? []), 1);
@endphp

<div class="report-container">
    <div class="report-header">
        <h1>Тошкент шахрини ривожлантириш жамғармаси</h1>
        <h2>Йиллик тушумлар бўйича маълумот (Свод 2 · {{ $statusLabel }})</h2>
        <div class="info">МАЪЛУМОТ</div>
    </div>

    <div class="table-wrap">
        <table class="report-table">
            <thead>
                <tr class="meta-row">
                    <th colspan="2" class="meta-left">{{ now()->format('d.m.Y') }}</th>
                    <th colspan="{{ $typeCount }}"></th>
                    <th colspan="4" class="meta-right">млн.сўм</th>
                </tr>
                <tr>
                    <th rowspan="3">Туманлар ва лойиҳалар кесимида</th>
                    <th rowspan="3">Жами тушумлар</th>
                    <th colspan="{{ $typeCount }}">Жумладан</th>
                    <th rowspan="3">Жумладан тақсимланган</th>
                    <th colspan="2">Жумладан</th>
                    <th rowspan="3">Тақсимланмаган қолдик</th>
                </tr>
                <tr>
                    @forelse($typeColumns as $type)
                        <th>{{ $extractCode($type) }}</th>
                    @empty
                        <th>—</th>
                    @endforelse
                    <th>3430188</th>
                    <th>3430482</th>
                </tr>
                <tr>
                    @forelse($typeColumns as $type)
                        <th>{{ $type }}</th>
                    @empty
                        <th>Турлар</th>
                    @endforelse
                    <th>Жамгармага<br>Отчисление 60.0 %</th>
                    <th>Бюджета<br>Отчисление 40.0 %</th>
                </tr>
            </thead>
            <tbody>
                @if(!empty($summaryRows))
                    <tr class="total-row">
                        <td>Жами</td>
                        <td class="num">{{ $fmt($totals['total_receipts'] ?? 0) }}</td>
                        @foreach($typeColumns as $type)
                            <td class="num">{{ $fmt($totals['types'][$type] ?? 0) }}</td>
                        @endforeach
                        @if(empty($typeColumns))
                            <td class="num">0,0</td>
                        @endif
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
                            @foreach($typeColumns as $type)
                                <td class="num">{{ $fmt($row['types'][$type] ?? 0) }}</td>
                            @endforeach
                            @if(empty($typeColumns))
                                <td class="num">0,0</td>
                            @endif
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

    <div class="print-row">
        <button onclick="window.print()" class="print-btn">Печать</button>
    </div>
</div>
@endsection
