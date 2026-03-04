@extends('layouts.app')

@section('title', 'Свод 2 - Йиллик ҳисобот')

@push('styles')
<style>
    .report-container {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
        padding: 24px;
    }

    .report-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #018c87;
    }

    .report-header h1 {
        font-size: 1.3rem;
        font-weight: bold;
        margin-bottom: 8px;
        color: #15191e;
    }

    .report-header h2 {
        font-size: 1.1rem;
        font-weight: 600;
        color: #018c87;
        margin-bottom: 10px;
    }

    .report-header .date {
        font-size: 0.9rem;
        color: #6e788b;
    }

    /* Modern table styling */
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        margin-bottom: 20px;
    }

    .modern-table thead th {
        padding: 14px 12px;
        text-align: center;
        font-weight: 600;
        color: #fff;
        background: #018c87;
        border: 1px solid #017570;
        white-space: nowrap;
    }

    .modern-table thead th:first-child {
        text-align: left;
        border-radius: 8px 0 0 0;
    }

    .modern-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }

    .modern-table tbody td {
        padding: 12px;
        border: 1px solid #e0e0e0;
        color: #333;
    }

    .modern-table tbody tr:nth-child(even) {
        background: #f7f9fa;
    }

    .modern-table tbody tr:hover {
        background: #e8f4f3;
    }

    .modern-table tbody td:first-child {
        font-weight: 500;
        text-align: left;
    }

    .modern-table tbody td:not(:first-child) {
        text-align: right;
    }

    /* Total row */
    .total-row {
        background: #e8f4f3 !important;
        font-weight: 700;
    }

    .total-row td {
        border-top: 2px solid #018c87;
        border-bottom: 2px solid #018c87;
    }

    /* Section headers */
    .section-header {
        background: #f0f2f5 !important;
        font-weight: 600;
        color: #15191e;
    }

    .section-header td {
        border-top: 2px solid #c0c0c0;
    }

    /* Print button */
    .print-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: #018c87;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }

    .print-btn:hover {
        background: #017570;
    }

    @media print {
        .platon-header,
        .platon-aside,
        .print-btn {
            display: none !important;
        }
        .platon-main {
            margin-left: 0 !important;
        }
        .report-container {
            box-shadow: none;
            border: none;
        }
    }
</style>
@endpush

@section('content')
<div class="report-container">
    {{-- Header --}}
    <div class="report-header">
        <h1>Тошкент шахрини ривожлантириш жамғармаси</h1>
        <h2>Йиллик тушумлар бўйича маълумот (Свод 2)</h2>
        <div class="date">{{ now()->format('d.m.Y') }}</div>
    </div>

    {{-- Yearly Summary Table --}}
    <div class="table-responsive">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Йил / Ой</th>
                    <th>Январь</th>
                    <th>Февраль</th>
                    <th>Март</th>
                    <th>Апрель</th>
                    <th>Май</th>
                    <th>Июнь</th>
                    <th>Июль</th>
                    <th>Август</th>
                    <th>Сентябрь</th>
                    <th>Октябрь</th>
                    <th>Ноябрь</th>
                    <th>Декабрь</th>
                    <th>Жами</th>
                </tr>
            </thead>
            <tbody>
                @foreach($years as $year)
                    <tr class="section-header">
                        <td colspan="14">{{ $year }} йил</td>
                    </tr>

                    {{-- Credit row --}}
                    <tr>
                        <td style="padding-left: 24px;">Кредит (Приход)</td>
                        @foreach($months as $index => $month)
                            <td>{{ number_format($yearlyData[$year]['credit'][$index + 1] ?? 0, 1, ',', ' ') }}</td>
                        @endforeach
                        <td style="font-weight: 700;">{{ number_format($yearlyData[$year]['credit_total'] ?? 0, 1, ',', ' ') }}</td>
                    </tr>

                    {{-- Debit row --}}
                    <tr>
                        <td style="padding-left: 24px;">Дебет (Расход)</td>
                        @foreach($months as $index => $month)
                            <td>{{ number_format($yearlyData[$year]['debit'][$index + 1] ?? 0, 1, ',', ' ') }}</td>
                        @endforeach
                        <td style="font-weight: 700;">{{ number_format($yearlyData[$year]['debit_total'] ?? 0, 1, ',', ' ') }}</td>
                    </tr>

                    {{-- Balance row --}}
                    <tr style="background: #e8f4f3;">
                        <td style="padding-left: 24px; font-weight: 600;">Қолдиқ</td>
                        @foreach($months as $index => $month)
                            <td style="font-weight: 600;">{{ number_format(($yearlyData[$year]['credit'][$index + 1] ?? 0) - ($yearlyData[$year]['debit'][$index + 1] ?? 0), 1, ',', ' ') }}</td>
                        @endforeach
                        <td style="font-weight: 700; color: #018c87;">{{ number_format(($yearlyData[$year]['credit_total'] ?? 0) - ($yearlyData[$year]['debit_total'] ?? 0), 1, ',', ' ') }}</td>
                    </tr>
                @endforeach

                {{-- Grand Total --}}
                <tr class="total-row">
                    <td>ЖАМИ</td>
                    @php
                    $grandTotalCredit = 0;
                    $grandTotalDebit = 0;
                    foreach($years as $year) {
                        $grandTotalCredit += $yearlyData[$year]['credit_total'] ?? 0;
                        $grandTotalDebit += $yearlyData[$year]['debit_total'] ?? 0;
                    }
                    @endphp
                    @foreach($months as $index => $month)
                        @php
                        $monthTotal = 0;
                        foreach($years as $year) {
                            $monthTotal += ($yearlyData[$year]['credit'][$index + 1] ?? 0) - ($yearlyData[$year]['debit'][$index + 1] ?? 0);
                        }
                        @endphp
                        <td>{{ number_format($monthTotal, 1, ',', ' ') }}</td>
                    @endforeach
                    <td>{{ number_format($grandTotalCredit - $grandTotalDebit, 1, ',', ' ') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Summary by District --}}
    <div style="margin-top: 30px;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 15px; color: #15191e;">Туманлар бўйича жами:</h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Туман</th>
                        <th>Жами Кредит</th>
                        <th>Жами Дебет</th>
                        <th>Қолдиқ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($districtSummary as $district => $data)
                    <tr>
                        <td>{{ $district }}</td>
                        <td>{{ number_format($data['credit'], 1, ',', ' ') }}</td>
                        <td>{{ number_format($data['debit'], 1, ',', ' ') }}</td>
                        <td style="font-weight: 600; color: {{ $data['balance'] >= 0 ? '#0bc33f' : '#e63260' }};">{{ number_format($data['balance'], 1, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Print Button --}}
    <div style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" class="print-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                <path d="M6 14h12v8H6z"/>
            </svg>
            Печать
        </button>
    </div>
</div>
@endsection
