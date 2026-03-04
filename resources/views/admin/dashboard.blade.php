@extends('layouts.app')
@section('title', 'IT Boshqaruv paneli')

@section('content')
{{-- Flash --}}
@if(session('success'))
<div class="platon-alert platon-alert-success" style="margin-bottom:20px">✓ {{ session('success') }}</div>
@endif
@if(session('cache_cleared'))
<div class="platon-alert platon-alert-success" style="margin-bottom:20px">✓ {{ session('cache_cleared') }}</div>
@endif

{{-- Stat cards --}}
<div class="stat-cards-row">
    <div class="stat-card-p sc-blue">
        <span class="sc-label">Foydalanuvchilar</span>
        <span class="sc-value">{{ $stats['users'] }}</span>
    </div>
    @if($stats['pending'] > 0)
    <div class="stat-card-p sc-yellow">
        <span class="sc-label">Kutilmoqda</span>
        <span class="sc-value">{{ $stats['pending'] }}</span>
    </div>
    @endif
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="admin-grid">
    {{-- Quick links --}}
    <div class="block">
        <div class="section-heading">Tezkor havolalar</div>
        <a href="{{ route('admin.users') }}" class="quick-link">
            <div class="quick-link-icon ql-yellow">👥</div>
            <div class="quick-link-info">
                <strong>Foydalanuvchilar</strong>
                <span>Rol, boshqarish</span>
            </div>
        </a>
        <a href="{{ route('dashboard') }}" class="quick-link">
            <div class="quick-link-icon ql-teal">📊</div>
            <div class="quick-link-info">
                <strong>Dashboard</strong>
                <span>Statistikalar</span>
            </div>
        </a>
        <a href="{{ route('home') }}" class="quick-link">
            <div class="quick-link-icon ql-green">📋</div>
            <div class="quick-link-info">
                <strong>Tranzaksiyalar</strong>
                <span>Barcha yozuvlar</span>
            </div>
        </a>
    </div>

    {{-- Cache management --}}
    <div class="block">
        <div class="section-heading">Kesh boshqaruvi</div>
        <p style="font-size:0.83rem;color:#6e788b;margin-bottom:14px;">
            Yangi ma'lumotlar import qilingandan so'ng keshni tozalang va qayta ishlang.
        </p>

        {{-- Clear cache --}}
        <form method="POST" action="{{ route('admin.clear-cache') }}" style="margin-bottom:12px;">
            @csrf
            <button type="submit" style="width:100%;padding:10px 18px;background:#e74c3c;color:#fff;border:none;border-radius:8px;font-size:0.875rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <span style="font-size:1.1rem;">🗑️</span> Keshni tozalash
            </button>
        </form>

        {{-- Warm cache --}}
        <form method="POST" action="{{ route('admin.warm-cache') }}">
            @csrf
            <button type="submit" style="width:100%;padding:10px 18px;background:#018c87;color:#fff;border:none;border-radius:8px;font-size:0.875rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <span style="font-size:1.1rem;">⚡</span> Keshni qayta qurish (issiq)
            </button>
        </form>
        <p style="font-size:0.75rem;color:#6e788b;margin-top:8px;">
            "Qayta qurish" barcha hisobotlarni oldindan hisoblab chiqadi &mdash; keyingi ochilish &lt;5ms.
        </p>
    </div>
</div>

<style>
@media(max-width:760px){ .admin-grid { grid-template-columns:1fr !important; } }
</style>
@endsection
