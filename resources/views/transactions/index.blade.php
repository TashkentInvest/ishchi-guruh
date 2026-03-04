@extends('layouts.app')

@section('title', 'Трансакциялар')

@push('styles')
<style>
    /* Table block styling matching reference UI */
    .table-block {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
        overflow: hidden;
    }

    .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e8e8e8;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .search-place {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1rem;
        font-weight: 600;
        color: #15191e;
    }

    .search-place input {
        border: 1px solid #dcddde;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 0.875rem;
        min-width: 200px;
        outline: none;
    }

    .search-place input:focus {
        border-color: #018c87;
    }

    .filters {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filter-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        background: #018c87;
        color: #fff;
        transition: all 0.15s;
    }

    .filter-btn:hover {
        background: #017570;
    }

    .filter-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Main table styling */
    .main-table {
        width: 100%;
        overflow-x: auto;
    }

    .main-table table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .main-table thead th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 600;
        color: #27314b;
        white-space: nowrap;
        background: #fff;
        border-bottom: 1px solid #e8e8e8;
    }

    .main-table thead th .th-inner {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .main-table thead th .th-inner:hover {
        color: #018c87;
    }

    .main-table thead th svg {
        width: 16px;
        height: 16px;
        opacity: 0.5;
    }

    .main-table tbody tr {
        border-bottom: 1px solid #f0f2f5;
        transition: background 0.1s;
    }

    .main-table tbody tr:hover {
        background: #f7f9fa;
    }

    .main-table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        color: #333;
    }

    /* Status badges */
    .status {
        display: inline-flex;
        align-items: center;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 500;
        padding: 5px 11px;
        border: 1px solid;
        white-space: nowrap;
    }

    .status.outline.success {
        background: rgba(6,184,56,.1);
        border-color: #0bc33f;
        color: #0bc33f;
    }

    .status.outline.danger {
        background: rgba(230,50,96,.1);
        border-color: #e63260;
        color: #e63260;
    }

    .status.outline.warning {
        background: rgba(254,197,36,.15);
        border-color: #fec524;
        color: #9a6800;
    }

    /* Action button */
    .action-btn {
        background: none;
        border: none;
        color: #6e788b;
        cursor: pointer;
        padding: 6px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }

    .action-btn:hover {
        background: #f0f2f5;
        color: #15191e;
    }

    .action-btn svg {
        width: 20px;
        height: 20px;
    }

    /* Pagination */
    .pagination-wrap {
        padding: 16px 20px;
        border-top: 1px solid #e8e8e8;
        display: flex;
        justify-content: center;
    }

    .pagination {
        display: flex;
        gap: 4px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .pagination li a,
    .pagination li span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        font-size: 0.875rem;
        text-decoration: none;
        color: #555;
        background: #fff;
        border: 1px solid #e0e0e0;
        transition: all 0.15s;
    }

    .pagination li a:hover {
        background: #f0f2f5;
        color: #15191e;
    }

    .pagination li.active span {
        background: #018c87;
        color: #fff;
        border-color: #018c87;
    }

    .pagination li.disabled span {
        color: #aab0bb;
        cursor: not-allowed;
    }

    /* Summary stats row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e8e8e8;
    }

    .stat-card .label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6e788b;
        margin-bottom: 8px;
    }

    .stat-card .value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #15191e;
    }

    .stat-card.primary .value { color: #018c87; }
    .stat-card.info .value { color: #1471f0; }
    .stat-card.success .value { color: #0bc33f; }
</style>
@endpush

@section('content')
<!-- Summary Stats -->
<div class="stats-row">
    <div class="stat-card primary">
        <div class="label">Жами Кредит</div>
        <div class="value">{{ number_format($summary['total_credit'], 0, ',', ' ') }} сўм</div>
    </div>
    <div class="stat-card info">
        <div class="label">Жами Дебет</div>
        <div class="value">{{ number_format($summary['total_debit'], 0, ',', ' ') }} сўм</div>
    </div>
    <div class="stat-card success">
        <div class="label">Жами Йозувлар</div>
        <div class="value">{{ number_format($summary['total_records'], 0, ',', ' ') }}</div>
    </div>
</div>

<!-- Filters Form -->
<form method="GET" action="{{ route('home') }}" id="filterForm">
    <div class="table-block mb-3">
        <div class="table-header">
            <div class="search-place">
                Трансакциялар
                <input type="text" name="search" placeholder="Қидириш..." value="{{ request('search') }}" onkeypress="if(event.key==='Enter') document.getElementById('filterForm').submit()">
            </div>
            <div class="filters">
                <select name="district" class="filter-btn" style="background: #fff; color: #333; border: 1px solid #dcddde;" onchange="document.getElementById('filterForm').submit()">
                    <option value="">Барча туманлар</option>
                    @foreach($districts as $district)
                        <option value="{{ $district }}" {{ request('district') == $district ? 'selected' : '' }}>
                            {{ $district }}
                        </option>
                    @endforeach
                </select>
                <select name="year" class="filter-btn" style="background: #fff; color: #333; border: 1px solid #dcddde;" onchange="document.getElementById('filterForm').submit()">
                    <option value="">Барча йиллар</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                            {{ $year }}
                        </option>
                    @endforeach
                </select>
                <select name="month" class="filter-btn" style="background: #fff; color: #333; border: 1px solid #dcddde;" onchange="document.getElementById('filterForm').submit()">
                    <option value="">Барча ойлар</option>
                    @foreach($months as $month)
                        <option value="{{ $month }}" {{ request('month') == $month ? 'selected' : '' }}>
                            {{ $month }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="filter-btn">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M6.768 4.066A2.5 2.5 0 113.232 7.6a2.5 2.5 0 013.536-3.535M16.667 5.833H7.5M16.768 12.399a2.5 2.5 0 11-3.536 3.535 2.5 2.5 0 013.536-3.535M3.333 14.167H12.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Филтр
                </button>
                <a href="{{ route('home') }}" class="filter-btn" style="background: #6e788b; text-decoration: none;">Тозалаш</a>
            </div>
        </div>
    </div>
</form>

<!-- Transactions Table -->
<div class="table-block">
    <div class="main-table">
        <table>
            <thead>
                <tr>
                    @php
                    $sortField = request('sort', 'id');
                    $sortDir   = request('dir', 'desc');
                    $nextDir   = $sortDir === 'asc' ? 'desc' : 'asc';
                    $qBase     = request()->except(['sort','dir','page']);
                    function sortUrl($field, $qBase, $sortField, $nextDir) {
                        $dir = ($sortField === $field) ? $nextDir : 'desc';
                        return url()->current() . '?' . http_build_query(array_merge($qBase, ['sort'=>$field,'dir'=>$dir]));
                    }
                    @endphp

                    @php
                    $cols = [
                        'id'       => 'ID',
                        'date'     => 'Сана',
                        'district' => 'Туман',
                        'type'     => 'Тури',
                    ];
                    @endphp

                    @foreach($cols as $col => $label)
                    <th>
                        <a href="{{ sortUrl($col, $qBase, $sortField, $nextDir) }}" class="th-inner" style="text-decoration:none; color:inherit;">
                            {{ $label }}
                            <svg viewBox="0 0 16 16" fill="none" style="{{ $sortField === $col ? 'opacity:1; color:#018c87;' : '' }}">
                                @if($sortField === $col && $sortDir === 'asc')
                                <path d="M8 2L4.667 6.667h6.666L8 2zM8 14l3.333-4.667H4.667L8 14z" fill="#018c87"/>
                                @elseif($sortField === $col)
                                <path d="M8 14l3.333-4.667H4.667L8 14zM8 2L4.667 6.667h6.666L8 2z" fill="#018c87"/>
                                @else
                                <path d="M8 14a.605.605 0 01-.467-.2L4.2 10.466a.644.644 0 010-.933.644.644 0 01.933 0L8 12.4l2.867-2.867a.644.644 0 01.933 0 .644.644 0 010 .933L8.467 13.8A.605.605 0 018 14zM4.667 6.667a.605.605 0 01-.467-.2.644.644 0 010-.934L7.533 2.2a.644.644 0 01.934 0L11.8 5.533a.644.644 0 010 .934.644.644 0 01-.933 0L8 3.6 5.133 6.467a.605.605 0 01-.466.2z" fill="#78829D"/>
                                @endif
                            </svg>
                        </a>
                    </th>
                    @endforeach

                    <th>Ой/Йил</th>
                    <th>
                        <a href="{{ sortUrl('flow', $qBase, $sortField, $nextDir) }}" class="th-inner" style="text-decoration:none; color:inherit;">
                            Поток
                            <svg viewBox="0 0 16 16" fill="none"><path d="M8 14a.605.605 0 01-.467-.2L4.2 10.466a.644.644 0 010-.933.644.644 0 01.933 0L8 12.4l2.867-2.867a.644.644 0 01.933 0 .644.644 0 010 .933L8.467 13.8A.605.605 0 018 14zM4.667 6.667a.605.605 0 01-.467-.2.644.644 0 010-.934L7.533 2.2a.644.644 0 01.934 0L11.8 5.533a.644.644 0 010 .934.644.644 0 01-.933 0L8 3.6 5.133 6.467a.605.605 0 01-.466.2z" fill="#78829D"/></svg>
                        </a>
                    </th>
                    <th>
                        <a href="{{ sortUrl('amount', $qBase, $sortField, $nextDir) }}" class="th-inner" style="text-decoration:none; color:inherit;">
                            Сумма
                            <svg viewBox="0 0 16 16" fill="none"><path d="M8 14a.605.605 0 01-.467-.2L4.2 10.466a.644.644 0 010-.933.644.644 0 01.933 0L8 12.4l2.867-2.867a.644.644 0 01.933 0 .644.644 0 010 .933L8.467 13.8A.605.605 0 018 14zM4.667 6.667a.605.605 0 01-.467-.2.644.644 0 010-.934L7.533 2.2a.644.644 0 01.934 0L11.8 5.533a.644.644 0 010 .934.644.644 0 01-.933 0L8 3.6 5.133 6.467a.605.605 0 01-.466.2z" fill="#78829D"/></svg>
                        </a>
                    </th>
                    <th>Тўлов мақсади</th>
                    <th>&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    <tr onclick="openDrawer({{ $transaction->id }}, '{{ addslashes($transaction->district) }}', '{{ $transaction->date->format('d.m.Y') }}', '{{ addslashes($transaction->type) }}', '{{ $transaction->month }}/{{ $transaction->year }}', '{{ addslashes($transaction->flow) }}', '{{ number_format($transaction->amount, 0, ',', ' ') }}', `{{ addslashes($transaction->payment_purpose) }}`)" style="cursor:pointer;">
                        <td>#{{ $transaction->id }}</td>
                        <td>{{ $transaction->date->format('d.m.Y') }}</td>
                        <td>{{ $transaction->district }}</td>
                        <td>{{ $transaction->type }}</td>
                        <td>{{ $transaction->month }} / {{ $transaction->year }}</td>
                        <td>
                            <span class="status outline {{ $transaction->flow == 'Приход' ? 'success' : 'danger' }}">
                                {{ $transaction->flow }}
                            </span>
                        </td>
                        <td style="font-weight: 600;">{{ number_format($transaction->amount, 0, ',', ' ') }} сўм</td>
                        <td style="max-width: 250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $transaction->payment_purpose }}">
                            {{ Str::limit($transaction->payment_purpose, 40) }}
                        </td>
                        <td onclick="event.stopPropagation()">
                            <button type="button" class="action-btn" title="Кўриш"
                                onclick="openDrawer({{ $transaction->id }}, '{{ addslashes($transaction->district) }}', '{{ $transaction->date->format('d.m.Y') }}', '{{ addslashes($transaction->type) }}', '{{ $transaction->month }}/{{ $transaction->year }}', '{{ addslashes($transaction->flow) }}', '{{ number_format($transaction->amount, 0, ',', ' ') }}', `{{ addslashes($transaction->payment_purpose) }}`)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="2.997" y="2.997" width="18.008" height="18.008" rx="5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12.5 8.499h3.002v3M11.5 15.502H8.499V12.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #6e788b;">
                            Маълумот топилмади
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">
        {{ $transactions->links() }}
    </div>
</div>

{{-- ─── Detail Drawer ─── --}}
<div id="drawer-overlay" onclick="closeDrawer()" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:200;"></div>
<div id="detail-drawer" style="
    position:fixed; top:0; right:-440px; width:420px; height:100vh;
    background:#fff; box-shadow:-4px 0 24px rgba(0,0,0,0.12);
    z-index:201; display:flex; flex-direction:column;
    transition: right 0.28s cubic-bezier(.4,0,.2,1);
    border-radius: 0;
">
    <div style="padding:20px 24px 16px; border-bottom:1px solid #f0f2f5; display:flex; align-items:center; justify-content:space-between;">
        <strong style="font-size:1rem; color:#15191e;">Трансакция маълумотлари</strong>
        <button onclick="closeDrawer()" style="background:none;border:none;cursor:pointer;color:#6e788b;padding:4px;border-radius:6px;" title="Йопиш">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round"/>
            </svg>
        </button>
    </div>
    <div style="padding:20px 24px; overflow-y:auto; flex:1;">
        <ul style="list-style:none; padding:0; margin:0;" id="drawer-content">
            {{-- filled by JS --}}
        </ul>
    </div>
</div>

@push('scripts')
<script>
function openDrawer(id, district, date, type, monthYear, flow, amount, purpose) {
    const items = [
        ['ID', '#' + id],
        ['Сана', date],
        ['Туман', district],
        ['Тури', type],
        ['Ой / Йил', monthYear],
        ['Поток', flow, flow.includes('Приход') ? '#0bc33f' : '#e63260'],
        ['Сумма', amount + " сўм"],
        ["Тўлов мақсади", purpose],
    ];

    const ul = document.getElementById('drawer-content');
    ul.innerHTML = items.map(([label, val, color]) => `
        <li style="padding:14px 0; border-bottom:1px solid #f0f2f5; display:flex; flex-direction:column; gap:4px;">
            <span style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#aab0bb;">${label}</span>
            <span style="font-size:0.9rem; color:${color||'#15191e'}; font-weight:${color?'700':'500'};">${val || '—'}</span>
        </li>
    `).join('');

    document.getElementById('drawer-overlay').style.display = 'block';
    document.getElementById('detail-drawer').style.right = '0';
}

function closeDrawer() {
    document.getElementById('drawer-overlay').style.display = 'none';
    document.getElementById('detail-drawer').style.right = '-440px';
}

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrawer();
});
</script>
@endpush
@endsection
