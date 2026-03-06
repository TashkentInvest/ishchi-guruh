@extends('layouts.app')

@section('title', 'Large Dataset Cache Builder')

@push('styles')
<style>
    .proc-wrap {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        padding: 18px;
        max-width: 980px;
    }

    .proc-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .proc-subtitle {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 16px;
    }

    .result-area {
        border: 1px solid #dbe2ea;
        background: #f8fafc;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 14px;
        font-size: 0.82rem;
        color: #334155;
    }

    .result-head {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }

    .result-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 8px;
        margin-bottom: 8px;
    }

    .result-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px;
    }

    .result-card .label {
        font-size: 0.72rem;
        color: #64748b;
    }

    .result-card .value {
        font-size: 0.92rem;
        font-weight: 700;
        color: #0f172a;
    }

    .result-list {
        margin: 6px 0 0;
        padding-left: 18px;
        color: #1e293b;
    }

    .proc-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .proc-select {
        min-width: 150px;
        border: 1px solid #d0d7df;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 0.82rem;
        background: #fff;
        color: #334155;
    }

    .proc-btn {
        border: none;
        border-radius: 8px;
        padding: 8px 13px;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        color: #fff;
        background: #018c87;
    }

    .proc-btn.secondary {
        background: #64748b;
    }

    .proc-btn:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    .percent-text {
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .progress-track {
        width: 100%;
        height: 14px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        border: 1px solid #d5dee8;
    }

    .progress-bar {
        width: 0;
        height: 100%;
        background: linear-gradient(90deg, #018c87 0%, #00b7ad 100%);
        transition: width 0.25s ease;
    }

    .status-text {
        margin-top: 9px;
        font-size: 0.82rem;
        color: #475569;
    }
</style>
@endpush

@section('content')
<div class="proc-wrap">
    <div class="proc-title">Large Dataset Cache Builder</div>
    <div class="proc-subtitle">File cache + polling architecture (no Redis, no queues, no workers)</div>

    <div class="result-area" id="resultArea"></div>

    <div class="proc-controls">
        <select id="chunkSize" class="proc-select">
            <option value="100">Chunk 100</option>
            <option value="250" selected>Chunk 250 (recommended)</option>
            <option value="500">Chunk 500</option>
        </select>

        <button type="button" id="startBtn" class="proc-btn">Start Processing</button>
        <button type="button" id="resetBtn" class="proc-btn secondary">Reset State</button>
    </div>

    <div class="percent-text" id="percentText">0%</div>
    <div class="progress-track">
        <div id="progressBar" class="progress-bar"></div>
    </div>
    <div class="status-text" id="statusText">Ready to start</div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const endpoints = {
        start: @json(route('processing.start')),
        progress: @json(route('processing.progress')),
        reset: @json(route('processing.reset')),
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const startBtn = document.getElementById('startBtn');
    const resetBtn = document.getElementById('resetBtn');
    const chunkSizeInput = document.getElementById('chunkSize');
    const percentText = document.getElementById('percentText');
    const progressBar = document.getElementById('progressBar');
    const statusText = document.getElementById('statusText');
    const resultArea = document.getElementById('resultArea');

    let pollTimer = null;

    const numberFmt = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 });

    const stopPolling = () => {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    };

    const startPolling = () => {
        if (pollTimer !== null) {
            return;
        }

        pollTimer = setInterval(() => {
            void fetchProgress(true);
        }, 1000);
    };

    const renderResult = (result) => {
        if (!result) {
            resultArea.innerHTML = '<div class="result-head">Result</div><div>Result will appear here when processing reaches 100%.</div>';
            return;
        }

        const districts = (result.top_districts || []).slice(0, 5)
            .map((item) => `<li>${item.district}: ${numberFmt.format(item.credit)}</li>`)
            .join('');

        resultArea.innerHTML = `
            <div class="result-head">Result</div>
            <div class="result-grid">
                <div class="result-card">
                    <div class="label">Total Rows</div>
                    <div class="value">${numberFmt.format(result.total_rows || 0)}</div>
                </div>
                <div class="result-card">
                    <div class="label">Total Credit</div>
                    <div class="value">${numberFmt.format(result.total_credit || 0)}</div>
                </div>
                <div class="result-card">
                    <div class="label">Total Debit</div>
                    <div class="value">${numberFmt.format(result.total_debit || 0)}</div>
                </div>
                <div class="result-card">
                    <div class="label">Duration (s)</div>
                    <div class="value">${numberFmt.format(result.duration_seconds || 0)}</div>
                </div>
            </div>
            <div><strong>Top Districts by Credit</strong></div>
            <ul class="result-list">${districts || '<li>No district data</li>'}</ul>
        `;
    };

    const renderState = (state) => {
        const percent = Number(state?.percent || 0);
        percentText.textContent = `${percent}%`;
        progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
        statusText.textContent = state?.message || 'Ready to start';

        const isRunning = state?.status === 'running';
        startBtn.disabled = isRunning;
        chunkSizeInput.disabled = isRunning;

        if (state?.status === 'completed') {
            renderResult(state.result || null);
            stopPolling();
        } else if (state?.status === 'running') {
            renderResult(state.result || null);
            startPolling();
        } else {
            renderResult(state?.result || null);
        }
    };

    const requestJson = async (url, options = {}) => {
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...(options.headers || {}),
                },
                ...options,
            });

            const payload = await response.json();
            return payload;
        } catch (error) {
            return {
                status: 'error',
                message: error instanceof Error ? error.message : 'Network error',
                percent: 0,
            };
        }
    };

    const fetchProgress = async (advance = true) => {
        const url = `${endpoints.progress}?advance=${advance ? 1 : 0}`;
        const state = await requestJson(url);
        renderState(state);
        return state;
    };

    startBtn.addEventListener('click', async () => {
        stopPolling();

        const body = new URLSearchParams();
        body.set('chunk_size', chunkSizeInput.value);

        const state = await requestJson(endpoints.start, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: body.toString(),
        });

        renderState(state);

        if (state?.status === 'running') {
            startPolling();
        }
    });

    resetBtn.addEventListener('click', async () => {
        stopPolling();

        const state = await requestJson(endpoints.reset, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        renderState(state);
    });

    const initialState = @json($initialState ?? []);
    renderState(initialState);

    if (initialState?.status === 'running') {
        startPolling();
    } else {
        void fetchProgress(false);
    }
})();
</script>
@endpush
