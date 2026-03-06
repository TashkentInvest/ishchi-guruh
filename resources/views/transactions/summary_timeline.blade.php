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

	.timeline-table th.year-toggle,
	.timeline-table th.month-toggle {
		cursor: pointer;
		user-select: none;
		transition: background 0.12s ease;
	}

	.timeline-table th.year-toggle:hover,
	.timeline-table th.month-toggle:hover {
		background: #b7e0d4;
	}

	.timeline-table th.year-toggle::after,
	.timeline-table th.month-toggle::after {
		content: '▸';
		display: inline-block;
		margin-left: 6px;
		font-size: 0.72rem;
		color: #0f766e;
	}

	.timeline-table th.year-toggle.expanded::after,
	.timeline-table th.month-toggle.expanded::after {
		content: '▾';
	}

	.timeline-table .col-month,
	.timeline-table .col-day,
	.timeline-table .group-month-head,
	.timeline-table .group-day-head {
		display: none;
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
			<div class="timeline-sub">Маълумотлар базадан олинади · Статус: <span class="status-pill">{{ $statusLabel }}</span> · Йилни босинг → ойлар, ойни босинг → кунлар</div>
		</div>
	</div>

	<div class="timeline-table-wrap">
		<table class="timeline-table" id="timeline-report-table">
			<thead>
				<tr>
					<th rowspan="2" class="sticky-left">Кўрсаткич</th>
					@if($yearCount > 0)
						<th class="group-head col-year group-year-head" colspan="{{ $yearCount }}">Йиллик</th>
					@endif
					@if($monthCount > 0)
						<th class="group-head col-month group-month-head" colspan="{{ $monthCount }}">Ойлик</th>
					@endif
					@if($dayCount > 0)
						<th class="group-head col-day group-day-head" colspan="{{ $dayCount }}">Кунлик</th>
					@endif
				</tr>
				<tr>
					@foreach($yearColumns as $column)
						@php($yearKey = substr($column['key'], 2))
						<th class="col-year year-toggle" data-year="{{ $yearKey }}" role="button" tabindex="0">{{ $column['label'] }}</th>
					@endforeach

					@foreach($monthColumns as $column)
						@php($monthKey = substr($column['key'], 2))
						<th class="col-month month-toggle" data-year="{{ substr($monthKey, 0, 4) }}" data-month="{{ $monthKey }}" role="button" tabindex="0">{{ $column['label'] }}</th>
					@endforeach

					@foreach($dayColumns as $column)
						@php($dayDate = $column['date'])
						<th class="col-day day-toggle" data-year="{{ substr($dayDate, 0, 4) }}" data-month="{{ substr($dayDate, 0, 7) }}">{{ $column['label'] }}</th>
					@endforeach
				</tr>
			</thead>
			<tbody>
				@if(!empty($mainRows) || !empty($allocationRows))
					<tr class="section-row">
						<td class="sticky-left">Тушумлар</td>
						<td class="section-fill" colspan="{{ $allCount - 1 }}"></td>
					</tr>

					@foreach($mainRows as $row)
						<tr class="{{ !empty($row['is_total']) ? 'total-row' : '' }}">
							<td class="sticky-left {{ empty($row['is_total']) ? 'type-row' : '' }}">{{ $row['label'] }}</td>

							@foreach($yearColumns as $column)
								<td class="num col-year">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($monthColumns as $column)
								@php($monthKey = substr($column['key'], 2))
								<td class="num col-month" data-year="{{ substr($monthKey, 0, 4) }}" data-month="{{ $monthKey }}">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($dayColumns as $column)
								@php($dayDate = $column['date'])
								<td class="num col-day" data-year="{{ substr($dayDate, 0, 4) }}" data-month="{{ substr($dayDate, 0, 7) }}" data-day="{{ $dayDate }}">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach
						</tr>
					@endforeach

					<tr class="section-row">
						<td class="sticky-left">Тақсимланган қисми</td>
						<td class="section-fill" colspan="{{ $allCount - 1 }}"></td>
					</tr>

					@foreach($allocationRows as $row)
						<tr class="{{ $loop->first ? 'total-row' : '' }}">
							<td class="sticky-left alloc-row">{{ $row['label'] }}</td>

							@foreach($yearColumns as $column)
								<td class="num col-year">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($monthColumns as $column)
								@php($monthKey = substr($column['key'], 2))
								<td class="num col-month" data-year="{{ substr($monthKey, 0, 4) }}" data-month="{{ $monthKey }}">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
							@endforeach

							@foreach($dayColumns as $column)
								@php($dayDate = $column['date'])
								<td class="num col-day" data-year="{{ substr($dayDate, 0, 4) }}" data-month="{{ substr($dayDate, 0, 7) }}" data-day="{{ $dayDate }}">{{ $fmt($row['values'][$column['key']] ?? 0) }}</td>
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
	const table = document.getElementById('timeline-report-table');

	if (!table) {
		return;
	}

	const yearHeaders = Array.from(table.querySelectorAll('th.year-toggle'));
	const monthHeaders = Array.from(table.querySelectorAll('th.month-toggle'));
	const dayHeaders = Array.from(table.querySelectorAll('th.day-toggle'));
	const monthCells = Array.from(table.querySelectorAll('td.col-month'));
	const dayCells = Array.from(table.querySelectorAll('td.col-day'));
	const monthGroupHead = table.querySelector('th.group-month-head');
	const dayGroupHead = table.querySelector('th.group-day-head');
	const sectionFillCells = Array.from(table.querySelectorAll('td.section-fill'));

	const expandedYears = new Set();
	const expandedMonths = new Set();

	const show = (element) => {
		if (!element) {
			return;
		}

		if (element.tagName === 'TH' || element.tagName === 'TD') {
			element.style.display = 'table-cell';
			return;
		}

		element.style.display = '';
	};

	const hide = (element) => {
		if (!element) {
			return;
		}

		element.style.display = 'none';
	};

	const setVisibility = (elements, predicate) => {
		let visibleCount = 0;

		elements.forEach((element) => {
			if (predicate(element)) {
				show(element);
				visibleCount += 1;
			} else {
				hide(element);
			}
		});

		return visibleCount;
	};

	const updateSectionFillColspan = () => {
		const visibleYears = yearHeaders.length;
		const visibleMonths = monthHeaders.filter((element) => element.style.display !== 'none').length;
		const visibleDays = dayHeaders.filter((element) => element.style.display !== 'none').length;
		const visibleColumns = visibleYears + visibleMonths + visibleDays;

		sectionFillCells.forEach((cell) => {
			cell.colSpan = Math.max(visibleColumns, 1);
		});
	};

	const refresh = () => {
		yearHeaders.forEach((header) => {
			const year = header.dataset.year;
			header.classList.toggle('expanded', expandedYears.has(year));
		});

		const visibleMonthHeaders = setVisibility(monthHeaders, (header) => {
			const year = header.dataset.year;
			const month = header.dataset.month;
			const isVisible = expandedYears.has(year);

			if (!isVisible) {
				expandedMonths.delete(month);
			}

			header.classList.toggle('expanded', expandedMonths.has(month));
			return isVisible;
		});

		setVisibility(monthCells, (cell) => expandedYears.has(cell.dataset.year));

		const visibleDayHeaders = setVisibility(dayHeaders, (header) => {
			return expandedMonths.has(header.dataset.month);
		});

		setVisibility(dayCells, (cell) => expandedMonths.has(cell.dataset.month));

		if (monthGroupHead) {
			if (visibleMonthHeaders > 0) {
				show(monthGroupHead);
				monthGroupHead.colSpan = visibleMonthHeaders;
			} else {
				hide(monthGroupHead);
			}
		}

		if (dayGroupHead) {
			if (visibleDayHeaders > 0) {
				show(dayGroupHead);
				dayGroupHead.colSpan = visibleDayHeaders;
			} else {
				hide(dayGroupHead);
			}
		}

		updateSectionFillColspan();
	};

	const toggleYear = (year) => {
		if (expandedYears.has(year)) {
			expandedYears.delete(year);

			monthHeaders.forEach((header) => {
				if (header.dataset.year === year) {
					expandedMonths.delete(header.dataset.month);
				}
			});
		} else {
			expandedYears.add(year);
		}

		refresh();
	};

	const toggleMonth = (year, month) => {
		if (!expandedYears.has(year)) {
			return;
		}

		if (expandedMonths.has(month)) {
			expandedMonths.delete(month);
		} else {
			expandedMonths.add(month);
		}

		refresh();
	};

	yearHeaders.forEach((header) => {
		const onToggle = () => toggleYear(header.dataset.year);

		header.addEventListener('click', onToggle);
		header.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				onToggle();
			}
		});
	});

	monthHeaders.forEach((header) => {
		const onToggle = () => toggleMonth(header.dataset.year, header.dataset.month);

		header.addEventListener('click', onToggle);
		header.addEventListener('keydown', (event) => {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				onToggle();
			}
		});
	});

	refresh();
})();
</script>
@endpush
