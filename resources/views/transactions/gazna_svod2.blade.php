@extends('layouts.app')

@section('title', 'Свод 2 — Газна')

@push('styles')
<style>
    .report-wrap {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
        padding: 20px;
    }

    .report-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .report-title h1 {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 4px;
        color: #15191e;
    }

    .report-title .sub {
        font-size: 0.8rem;
        color: #6e788b;
    }

    .switch-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .switch-link {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border: 1px solid #d7dde1;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #27314b;
        text-decoration: none;
        background: #fff;
    }

    .switch-link.active {
        background: #018c87;
        border-color: #018c87;
        color: #fff;
    }

    .print-btn {
        border: none;
        background: #018c87;
        color: #fff;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
    }

    .csv-wrap {
        overflow: auto;
        border: 1px solid #e8e8e8;
        border-radius: 10px;
        max-height: 72vh;
    }

    .csv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 1200px;
    }

    .csv-table td {
        border: 1px solid #ececec;
        padding: 8px 10px;
        white-space: nowrap;
        color: #27314b;
    }

    .csv-table tr:nth-child(even) {
        background: #fafafa;
    }

    .csv-table tr:nth-child(-n+6) td {
        background: #f0f9f8;
        font-weight: 600;
        color: #015c58;
    }

    .truncated-note {
        margin-top: 10px;
        font-size: 0.76rem;
        color: #9a6800;
        background: #fff7db;
        border: 1px solid #f4d88a;
        border-radius: 8px;
        padding: 8px 10px;
    }

    @media print {
        .platon-header,
        .platon-aside,
        .switch-row,
        .print-btn {
            display: none !important;
        }

        .platon-main {
            margin-left: 0 !important;
        }

        .report-wrap {
            box-shadow: none;
            border: none;
            padding: 0;
        }
    }
</style>
@endpush

@section('content')
<div class="report-wrap">
    <div class="report-head">
        <div class="report-title">
            <h1>Газна — Свод 2</h1>
            <div class="sub">Манба: storage/app/public/detalization/GAZNA_SVOD2.csv</div>
        </div>
        <button onclick="window.print()" class="print-btn">Печать</button>
    </div>

    <div class="switch-row">
        <a href="{{ route('summary', ['status' => 'jamgarma']) }}" class="switch-link">Jamgarma</a>
        <a href="{{ route('summary', ['status' => 'gazna']) }}" class="switch-link active">Gazna</a>
        <a href="{{ route('gazna.svod2') }}" class="switch-link active">Gazna Svod2</a>
        <a href="{{ route('gazna.svod3') }}" class="switch-link">Gazna Svod3</a>
        <a href="{{ route('jamgarma.yol') }}" class="switch-link">Jamgarma YOL</a>
    </div>

    @if(empty($rows))
        <div class="alert alert-warning mb-0">CSV топилмади ёки ўқиб бўлмади.</div>
    @else
        <div class="csv-wrap">
            <table class="csv-table">
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell !== '' ? $cell : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($isTruncated)
            <div class="truncated-note">Жадвал қисқартирилди: фақат биринчи йирик қисми кўрсатилди.</div>
        @endif
    @endif
</div>
@endsection
