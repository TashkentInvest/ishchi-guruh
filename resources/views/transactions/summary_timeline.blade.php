@extends('layouts.app')

@section('title', 'Свод — Вақт кесимида')

@push('styles')
<style>
	.timeline-wrap {
		background: #fff;
		border-radius: 12px;
		border: 1px solid #e5e7eb;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
		overflow: hidden;
	}

	.timeline-head {
		padding: 14px 16px;
		border-bottom: 1px solid #e5e7eb;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		flex-wrap: wrap;
		background: #f8fafc;
	}

	.timeline-title {
		font-size: 0.95rem;
		font-weight: 700;
		color: #111827;
	}

	.timeline-sub {
		font-size: 0.78rem;
		color: #6b7280;
		margin-top: 3px;
	}

	.timeline-controls {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		flex-wrap: wrap;
	}

	.toggle-btn {
		border: 1px solid #d1d5db;
		background: #fff;
		color: #334155;
		border-radius: 8px;
		font-size: 0.78rem;
		font-weight: 700;
		padding: 6px 10px;
		cursor: pointer;
	}

	.toggle-btn.active {
		border-color: #0f766e;
		background: #0f766e;
		color: #fff;
	}

	.status-pill {
		display: inline-block;
		background: #e0f2fe;
		border: 1px solid #bae6fd;
		color: #075985;
		border-radius: 999px;
		padding: 4px 10px;
		font-size: 0.72rem;
		font-weight: 700;
		letter-spacing: 0.03em;
		text-transform: uppercase;
	}

	.timeline-table-wrap {
		overflow: auto;
		max-height: calc(100vh - 260px);
	}

	.timeline-table {
		width: 100%;
		min-width: 1800px;
		border-collapse: collapse;
		font-size: 0.8rem;
	}

	.timeline-table th,
	.timeline-table td {
		border: 1px solid #e5e7eb;
		padding: 6px 8px;
		white-space: nowrap;
		vertical-align: middle;
	}

	.timeline-table thead th {
		background: #e2f3ed;
		color: #0f172a;
		text-align: center;
		font-weight: 700;
	}

	.timeline-table thead th.sticky-left,
	.timeline-table tbody td.sticky-left {
		position: sticky;
		left: 0;
		z-index: 5;
		background: #fff;
	}

	.timeline-table thead th.sticky-left {
		z-index: 8;
		background: #d6eee6;
		min-width: 290px;
		text-align: left;
	}

	.timeline-table thead th.group-head {
		background: #cce8dc;
		font-size: 0.76rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
	}

	.timeline-table tbody tr.total-row td {
		font-weight: 800;
		background: #f8fafc;
	}

	.timeline-table tbody tr.section-row td {
		background: #f1f5f9;
		font-style: italic;
		font-weight: 700;
		color: #334155;
	}

	.timeline-table td.num {
		text-align: right;
		font-variant-numeric: tabular-nums;
	}

	.timeline-table td.type-row {
		color: #1f2937;
	}

	.timeline-table td.alloc-row {
		color: #0f172a;
		font-weight: 600;
	}

	.empty-note {
		text-align: center;
		color: #64748b;
		padding: 20px;
	}

	@media print {
		.platon-aside,
		.platon-header,
		.timeline-head {
			display: none !important;
		}

		.platon-main {
			margin-left: 0 !important;
		}
	}
</style>
@endpush

@section('content')
@php
	$fmt = function ($value) {
		$numeric = (float) $value;

		if (abs($numeric) < 0.00001) {
			return '-';
		}

		return number_format($numeric, 2, ',', ' ');
	};

	$statusLabel = ($activeStatus ?? null) === 'gazna'
		? 'Gazna'
		: (($activeStatus ?? null) === 'jamgarma' ? 'Jamgarma' : 'Gazna + Jamgarma');

	$yearColumns = $yearColumns ?? [];
	$monthColumns = $monthColumns ?? [];
	$dayColumns = $dayColumns ?? [];
	$mainRows = $mainRows ?? [];
	$allocationRows = $allocationRows ?? [];

	$yearCount = count($yearColumns);
	$monthCount = count($monthColumns);
	$dayCount = count($dayColumns);

	$allCount = 1 + $yearCount + $monthCount + $dayCount;
@endphp

<div class="timeline-wrap">
	<div class="timeline-head">
		<div>
			<div class="timeline-title">Йиллик / ойлик / кунлик кесимда тушумлар ҳисоботи</div>
			<div class="timeline-sub">Маълумотлар базадан олинади · Статус: <span class="status-pill">{{ $statusLabel }}</span></div>
		</div>

		<div class="timeline-controls">
			<button type="button" class="toggle-btn active" data-group="year">Йиллик</button>
			<button type="button" class="toggle-btn active" data-group="month">Ойлик</button>
			<button type="button" class="toggle-btn active" data-group="day">Кунлик</button>
		</div>
	</div>

	<div class="timeline-table-wrap">
		<table class="timeline-table" id="timeline-report-table">
			<thead>
				<tr>
					<th rowspan="2" class="sticky-left">Кўрсаткич</th>
					@if($yearCount > 0)
						<th class="group-head col-year" colspan="{{ $yearCount }}">Йиллик</th>
					@endif
					@if($monthCount > 0)
						<th class="group-head col-month" colspan="{{ $monthCount }}">Ойлик</th>
					@endif
					@if($dayCount > 0)
						<th class="group-head col-day" colspan="{{ $dayCount }}">Кунлик</th>
					@endif
				</tr>
				<tr>
					@foreach($yearColumns as $column)
						<th class="col-year">{{ $column['label'] }}</th>
					@endforeach

					@foreach($monthColumns as $column)
						<th class="col-month">{{ $column['label'] }}</th>
					@endforeach

					@foreach($dayColumns as $column)
						<th class="col-day">{{ $column['label'] }}</th>
					@endforeach
				</tr>
			</thead>
			<tbody>
				@if(!empty($mainRows) || !empty($allocationRows))
					<tr class="section-row">
						<td class="sticky-left">Тушумлар</td>
						<td colspan="{{ $allCount - 1 }}"></td>
					</tr>

					@foreach($mainRows as $row)
						<tr class="{{ !empty($row['is_total']) ? 'total-row' : '' }}">
							<td class="sticky-left {{ empty($row['is_total']) ? 'type-row' : '' }}">{{ $row['label'] }}</td>

							@foreach($yearColumns as $column)
								<td class="num col-year">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($monthColumns as $column)
								<td class="num col-month">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($dayColumns as $column)
								<td class="num col-day">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach
						</tr>
					@endforeach

					<tr class="section-row">
						<td class="sticky-left">Тақсимланган қисми</td>
						<td colspan="{{ $allCount - 1 }}"></td>
					</tr>

					@foreach($allocationRows as $row)
						<tr class="{{ $loop->first ? 'total-row' : '' }}">
							<td class="sticky-left alloc-row">{{ $row['label'] }}</td>

							@foreach($yearColumns as $column)
								<td class="num col-year">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($monthColumns as $column)
								<td class="num col-month">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($dayColumns as $column)
								<td class="num col-day">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach
						</tr>
					@endforeach
				@else
					<tr>
						<td class="empty-note" colspan="{{ $allCount }}">Маълумот йўқ</td>
					</tr>
				@endif
			</tbody>
		</table>
	</div>
</div>
@endsection

@push('scripts')
<script>
(() => {
	const buttons = document.querySelectorAll('.toggle-btn[data-group]');

	if (!buttons.length) {
		return;
	}

	buttons.forEach((button) => {
		button.addEventListener('click', () => {
			const group = button.getAttribute('data-group');
			const isActive = button.classList.toggle('active');

			document.querySelectorAll('.col-' + group).forEach((cell) => {
				cell.style.display = isActive ? '' : 'none';
			});
		});
	});
})();
</script>
@endpush
