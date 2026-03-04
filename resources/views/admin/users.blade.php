@extends('layouts.app')
@section('title', 'Foydalanuvchilar — Admin')

@section('content')

@if(session('success'))
<div class="platon-alert platon-alert-success" style="margin-bottom:20px">✓ {{ session('success') }}</div>
@endif
@if(session('error'))
<div class="platon-alert platon-alert-danger" style="margin-bottom:20px">✗ {{ session('error') }}</div>
@endif

{{-- Filter toolbar --}}
<form method="GET" action="{{ route('admin.users') }}">
<div class="block" style="margin-bottom:20px;padding:16px 20px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:200px">
            <div class="platon-search" style="width:100%">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="#aab0bb" stroke-width="1.5" style="margin-left:10px;flex-shrink:0"><circle cx="9" cy="9" r="6"/><path stroke-linecap="round" d="M14 14l3 3"/></svg>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Ism, email, PINFL...">
            </div>
        </div>
        <div>
            <select name="role" class="form-select form-select-sm" style="min-width:160px">
                <option value="">Barcha rollar</option>
                @foreach($allRoles as $val => $lbl)
                <option value="{{ $val }}" {{ request('role') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="platon-btn platon-btn-primary platon-btn-sm">Filter</button>
        <a href="{{ route('admin.users') }}" class="platon-btn platon-btn-outline platon-btn-sm">Tozalash</a>
        <button type="button" onclick="openCreateUser()" class="platon-btn platon-btn-success platon-btn-sm" style="margin-left:auto">+ Yangi foydalanuvchi</button>
    </div>
</div>
</form>

<div class="block">
    <div class="section-heading">
        Foydalanuvchilar <span class="sbadge sbadge-gray" style="font-size:0.8rem">{{ $users->total() }}</span>
    </div>
    <div class="platon-table-wrap">
        <table class="platon-table">
            <thead>
                <tr>
                    <th>Foydalanuvchi</th>
                    <th>Rol</th>
                    <th>E-IMZO</th>
                    <th>Amallar</th>
                </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:34px;height:34px;border-radius:50%;background:rgba(1,140,135,0.1);display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:700;color:#018c87;flex-shrink:0">
                            {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.85rem">{{ $user->name }}</div>
                            @if($user->email)
                            <div style="font-size:0.75rem;color:#6e788b">{{ $user->email }}</div>
                            @endif
                            @if($user->pinfl)
                            <div style="font-size:0.72rem;color:#aab0bb;font-family:monospace">PINFL: {{ $user->pinfl }}</div>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    @php $rl = $allRoles[$user->role] ?? $user->role; @endphp
                    <span class="sbadge {{ $user->isAdmin() ? 'sbadge-purple' : 'sbadge-blue' }}">{{ $rl }}</span>
                </td>
                <td>
                    @if($user->serial_number)
                        <span class="sbadge {{ $user->isCertificateValid() ? 'sbadge-success' : 'sbadge-danger' }}" style="font-size:0.72rem">
                            {{ $user->isCertificateValid() ? 'Faol' : 'Muddati o\'tgan' }}
                        </span>
                        @if($user->certificate_valid_to)
                        <div style="font-size:0.7rem;color:#6e788b;margin-top:2px">{{ $user->certificate_valid_to->format('d.m.Y') }}</div>
                        @endif
                    @else
                        <span class="sbadge sbadge-gray" style="font-size:0.72rem">Yo'q</span>
                    @endif
                </td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap">
                        <a href="{{ route('admin.users.edit', $user) }}" class="platon-btn platon-btn-outline platon-btn-sm">Tahrir</a>
                        @if(auth()->id() !== $user->id)
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('{{ addslashes($user->name) }} ni o\'chirasizmi? Bu amalni bekor qilib bo\'lmaydi.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="platon-btn platon-btn-danger platon-btn-sm" style="opacity:0.7">O'ch</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align:center;padding:40px;color:#aab0bb">Foydalanuvchilar topilmadi</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div style="margin-top:16px;display:flex;justify-content:center">{{ $users->links() }}</div>
    @endif
</div>

{{-- Create User Modal --}}
<div id="create-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:500;align-items:center;justify-content:center;overflow-y:auto">
    <div style="background:#fff;border-radius:20px;padding:28px;width:560px;max-width:95vw;position:relative;margin:auto">
        <button onclick="document.getElementById('create-modal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.2rem;cursor:pointer;color:#6e788b">✕</button>
        <h5 style="font-weight:700;margin-bottom:20px">Yangi foydalanuvchi yaratish</h5>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label small fw-semibold">To'liq ism <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="255" value="{{ old('name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">PINFL</label>
                    <input type="text" name="pinfl" class="form-control" maxlength="20" value="{{ old('pinfl') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Parol <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Rol <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="">— Tanlang —</option>
                        @foreach($allRoles as $val => $lbl)
                        <option value="{{ $val }}" {{ old('role') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="platon-btn platon-btn-primary" style="width:100%">Yaratish</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openCreateUser() {
    document.getElementById('create-modal').style.display = 'flex';
}
document.getElementById('create-modal').addEventListener('click', function(e){
    if (e.target === this) this.style.display = 'none';
});
@if($errors->any())
document.getElementById('create-modal').style.display = 'flex';
@endif
</script>
@endpush
@endsection
