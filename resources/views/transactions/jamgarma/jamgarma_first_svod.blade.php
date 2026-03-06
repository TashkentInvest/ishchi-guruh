@extends('layouts.app')

@section('title', 'Свод — Тошкент шаҳрини ривожлантириш жамғармаси')

@push('styles')
<style>
    .tbl-block {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        border: 1px solid #e8e8e8;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .tbl-block-header {
        padding: 14px 20px;
        border-bottom: 1px solid #e8e8e8;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .tbl-block-header .title {
        font-size: 0.95rem;
        font-weight: 600;
        color: #15191e;
    }

    .tbl-block-header .sub {
        font-size: 0.75rem;
        color: #6e788b;
    }

    .report-band {
        background: linear-gradient(100deg, #f0f9f8, #e6f7f6);
        border: 1px solid #b2e4e1;
        border-radius: 12px;
        padding: 18px 24px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .report-band h1 {
        font-size: 1rem;
        font-weight: 700;
        color: #015c58;
        margin: 0 0 4px;
    }

    .report-band .rdate {
        font-size: 0.78rem;
        color: #6e788b;
    }

    .report-band .rtag {
        display: inline-block;
        background: #018c87;
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        padding: 3px 12px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .svod-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .svod-table thead th {
        padding: 10px 12px;
        font-weight: 600;
        font-size: 0.75rem;
        color: #fff;
        background: #018c87;
        border: 1px solid #017570;
        text-align: center;
        vertical-align: middle;
        line-height: 1.3;
    }

    .svod-table thead th:first-child {
        text-align: left;
    }

    .svod-table tbody tr {
        border-bottom: 1px solid #e8e8e8;
        transition: background .1s;
    }

    .svod-table tbody tr:hover {
        background: #f0f9f8;
    }

    .svod-table tbody td {
        padding: 11px 12px;
        border: 1px solid #ebebeb;
        color: #27314b;
    }

    .svod-table tbody td.num {
        text-align: right;
        font-weight: 500;
    }

    .svod-table tbody td.district-name {
        font-weight: 600;
        color: #15191e;
    }

    .svod-table .total-row td {
        background: #e8f4f3;
        font-weight: 700;
        color: #015c58;
        border-top: 2px solid #018c87;
        border-bottom: 2px solid #018c87;
    }

    .svod-table .incl-row td {
        background: #fafafa;
        color: #6e788b;
        font-style: italic;
        font-size: 0.8rem;
    }

    .print-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        background: #018c87;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .15s;
        text-decoration: none;
    }

    .print-btn:hover {
        background: #017570;
        color: #fff;
    }

    @media print {
        .print-btn,
        .report-band .rtag {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')
@php($formatNumber = fn ($value) => number_format((float) $value, 2, '.', ' '))

<div class="report-band">
    <div>
        <h1>"Тошкент шаҳрини ривожлантириш жамғармасига тушган маблағлар бўйича"</h1>
        <div class="rdate">
            {{ now()->format('d.m.Y') }} &nbsp;·&nbsp; Туманлар ва лойиҳалар кесимида
            &nbsp;·&nbsp; <strong>млн.сўм</strong>
        </div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
        <span class="rtag">МАЪЛУМОТ</span>
        <button onclick="window.print()" class="print-btn" type="button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                <path d="M6 14h12v8H6z"/>
            </svg>
            Печать
        </button>
    </div>
</div>

<div class="tbl-block">
    <div class="tbl-block-header">
        <span class="title">Туманлар ва лойиҳалар кесимида</span>
        <span class="sub">Жами тушум (млн.сўм)</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="svod-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width:22%; text-align:left;">Туманлар ва лойиҳалар</th>
                    <th rowspan="2" style="background:#015c58;">Жами</th>
                    <th rowspan="2">Йўл ҳаракати<br>қоидалари<br>бузилганлиги<br>учун жарималар</th>
                    <th colspan="4" style="background:#016d69; border-bottom:2px solid #fff;">Жумладан</th>
                    <th rowspan="2">Реклама<br>учун<br>тўлов 20%</th>
                </tr>
                <tr>
                    <th style="background:#019b96;">Жарима 10%<br>(хавфсиз шаҳар)</th>
                    <th style="background:#019b96;">Жарима 35%<br>(автоматлаштирилган)</th>
                    <th style="background:#019b96;">Жарима 5%<br>(1 йил ичида)</th>
                    <th style="background:#019b96;">Жарима 10%<br>(1 йилдан кейин)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="total-row">
                    <td class="district-name">Жами</td>
                    <td class="num">{{ $formatNumber($totals['total'] ?? 0) }}</td>
                    <td class="num">{{ $formatNumber($totals['penalties_total'] ?? 0) }}</td>
                    <td class="num">{{ $formatNumber($totals['safe_city_10'] ?? 0) }}</td>
                    <td class="num">{{ $formatNumber($totals['automated_35'] ?? 0) }}</td>
                    <td class="num">{{ $formatNumber($totals['fine_5_year'] ?? 0) }}</td>
                    <td class="num">{{ $formatNumber($totals['fine_10_after'] ?? 0) }}</td>
                    <td class="num">{{ $formatNumber($totals['reklama_20'] ?? 0) }}</td>
                </tr>
                <tr class="incl-row">
                    <td colspan="8" style="padding: 6px 12px;">жумладан:</td>
                </tr>

                @forelse(($rows ?? []) as $row)
                    <tr>
                        <td class="district-name">{{ $row['district'] }}</td>
                        <td class="num">{{ $formatNumber($row['total']) }}</td>
                        <td class="num">{{ $formatNumber($row['penalties_total']) }}</td>
                        <td class="num">{{ $formatNumber($row['safe_city_10']) }}</td>
                        <td class="num">{{ $formatNumber($row['automated_35']) }}</td>
                        <td class="num">{{ $formatNumber($row['fine_5_year']) }}</td>
                        <td class="num">{{ $formatNumber($row['fine_10_after']) }}</td>
                        <td class="num">{{ $formatNumber($row['reklama_20']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 18px;">Маълумот топилмади</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
