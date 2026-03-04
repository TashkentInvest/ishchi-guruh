@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    use App\Models\Transaction;
    $user = auth()->user();

    $totalTransactions = Transaction::count();
    $totalCredit = Transaction::sum('credit_amount');
    $totalDebit = Transaction::sum('debit_amount');
    $uniqueDistricts = Transaction::distinct()->count('district');
@endphp

{{-- Welcome --}}
<div style="margin-bottom:20px">
    <div style="font-size:1.1rem;font-weight:700;color:#15191e">
        Xush kelibsiz, {{ $user->name }}
    </div>
    <div style="font-size:0.85rem;color:#6e788b;margin-top:3px">
        {{ $user->role }}
    </div>
</div>

{{-- Stat cards --}}
<div class="stat-cards-row">
    <div class="stat-card-p sc-teal">
        <div class="sc-label">Jami tranzaksiyalar</div>
        <div class="sc-value">{{ number_format($totalTransactions) }}</div>
    </div>
    <div class="stat-card-p sc-green-dk">
        <div class="sc-label">Jami kredit</div>
        <div class="sc-value">{{ number_format($totalCredit, 0, ',', ' ') }}</div>
    </div>
    <div class="stat-card-p sc-orange">
        <div class="sc-label">Jami debet</div>
        <div class="sc-value">{{ number_format($totalDebit, 0, ',', ' ') }}</div>
    </div>
    <div class="stat-card-p sc-blue">
        <div class="sc-label">Tumanlar</div>
        <div class="sc-value">{{ $uniqueDistricts }}</div>
    </div>
</div>

{{-- Quick links --}}
<div class="row g-3">
    <div class="col-lg-6">
        <div class="block">
            <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#aab0bb;margin-bottom:16px">Tez o'tish</div>

            <a href="{{ route('home') }}" class="quick-link">
                <div class="quick-link-icon ql-teal">📋</div>
                <div class="quick-link-info">
                    <strong>Tranzaksiyalar ro'yxati</strong>
                    <span>Barcha yozuvlarni ko'rish</span>
                </div>
            </a>

            <a href="{{ route('dashboard') }}" class="quick-link">
                <div class="quick-link-icon ql-blue">📊</div>
                <div class="quick-link-info">
                    <strong>Dashboard</strong>
                    <span>Statistikalar</span>
                </div>
            </a>

            @if($user->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="quick-link">
                <div class="quick-link-icon ql-green">⚙️</div>
                <div class="quick-link-info">
                    <strong>Admin panel</strong>
                    <span>Boshqaruv</span>
                </div>
            </a>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="block h-100">
            <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#aab0bb;margin-bottom:16px">Ma'lumot</div>
            <div style="font-size:0.85rem;color:#5a6a8a;line-height:1.6;margin-bottom:14px">
                <strong style="color:#15191e">Tranzaksiyalar tizimi</strong> - Toshkent shahar
                tumanlari bo'yicha moliyaviy operatsiyalarni kuzatish va tahlil qilish.
            </div>
            <div style="font-size:0.82rem;color:#6e788b;line-height:1.8">
                <div>• DATASET.csv - asosiy tranzaksiyalar ma'lumoti</div>
                <div>• formulas.csv - hisoblash formulalari</div>
                <div>• Tumanlar bo'yicha filtrlash imkoniyati</div>
                <div>• Oy/yil bo'yicha guruhlash</div>
            </div>
        </div>
    </div>
</div>

@endsection
