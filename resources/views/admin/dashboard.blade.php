@extends('layouts.app')
@section('title', 'IT Boshqaruv paneli')

@section('content')
{{-- Flash --}}
@if(session('success'))
<div class="platon-alert platon-alert-success" style="margin-bottom:20px">✓ {{ session('success') }}</div>
@endif

{{-- Stat cards --}}
<div class="stat-cards-row">
    <div class="stat-card-p sc-blue">
        <span class="sc-label">Foydalanuvchilar</span>
        <span class="sc-value">{{ $stats['users'] }}</span>
    </div>
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
</div>

<style>
@media(max-width:760px){ .admin-grid { grid-template-columns:1fr !important; } }
</style>
@endsection
