<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tutash Hudud — Ijara Shartnomasi Arizasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #eef2f5; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; -webkit-text-size-adjust: 100%; }

        /* ─── Header ─── */
        .site-header { background: #018c87; padding: 14px 32px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 12px rgba(1,140,135,.25); }
        .site-header .brand { color: #fff; font-size: 1.1rem; font-weight: 800; text-decoration: none; letter-spacing: .02em; }
        .site-header .brand span { font-weight: 400; opacity: .85; font-size: .9rem; }
        .btn-header { border: 1.5px solid rgba(255,255,255,.4); background: transparent; color: #fff; padding: 7px 20px; border-radius: 8px; font-size: .9rem; cursor: pointer; text-decoration: none; transition: all .2s; display:inline-flex;align-items:center;gap:6px; }
        .btn-header:hover { background: rgba(255,255,255,.15); color: #fff; border-color: #fff; }
        .header-user { display:flex;align-items:center;gap:10px; }
        .header-name { color:rgba(255,255,255,.9);font-size:.85rem;font-weight:600; }
        .btn-logout-sm { border:1.5px solid rgba(255,255,255,.35);background:transparent;color:rgba(255,255,255,.85);padding:5px 13px;border-radius:7px;font-size:.8rem;cursor:pointer;transition:all .2s; }
        .btn-logout-sm:hover { background:rgba(255,255,255,.15);color:#fff;border-color:#fff; }

        /* ─── Page ─── */
        .main { flex: 1; display: flex; justify-content: center; padding: 28px 16px 40px; }
        .page-wrap { width: 100%; max-width: 880px; }

        /* ─── Quick cards (login / track) ─── */
        /* ─── Compact utility bar (login + track) ─── */
        .util-bar { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.06); display:flex; align-items:stretch; overflow:hidden; }
        .util-login { display:flex; align-items:center; gap:10px; padding:13px 20px; text-decoration:none; color:#15191e; flex-shrink:0; transition:background .15s; border-right:1px solid #f0f2f5; }
        .util-login:hover { background:#f0faf9; }
        .util-login-icon { font-size:1.5rem; line-height:1; }
        .util-login strong { display:block; font-size:.88rem; color:#018c87; line-height:1.25; white-space:nowrap; }
        .util-login small  { font-size:.72rem; color:#94a3b8; display:block; white-space:nowrap; }
        .util-track { flex:1; padding:11px 18px; display:flex; flex-direction:column; justify-content:center; }
        .util-track-lbl { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; margin-bottom:5px; }
        .util-track-row { display:flex; gap:7px; }
        .util-track-row input { flex:1; border:1.5px solid #dde3ee; border-radius:8px; padding:7px 11px; font-size:.88rem; color:#2c3e60; outline:none; min-width:0; background:#fafbfd; }
        .util-track-row input:focus { border-color:#018c87; box-shadow:0 0 0 3px rgba(1,140,135,.1); background:#fff; }
        .util-track-row button { white-space:nowrap; padding:7px 16px; background:#018c87; color:#fff; border:none; border-radius:8px; font-size:.88rem; font-weight:600; cursor:pointer; transition:background .15s; }
        .util-track-row button:hover { background:#017570; }
        .form-control, .form-select { border: 1.5px solid #dde3ee; border-radius: 8px; padding: 9px 13px; font-size: .92rem; color: #2c3e60; transition: border-color .2s, box-shadow .2s; }
        .form-control:focus, .form-select:focus { border-color: #018c87; box-shadow: 0 0 0 3px rgba(1,140,135,.1); outline: none; }

        /* ─── Official Document card ─── */
        .dalo-doc { background: #fff; border-top: 4px solid #009AB6; box-shadow: 0 6px 28px rgba(0,0,0,.09); padding: 36px 40px 28px; }

        /* Document header */
        .dalo-doc-head { text-align: center; margin-bottom: 20px; }
        .dalo-emblem { height: 58px; margin: 0 auto 10px; display: block; }
        .dalo-title { font-family: 'Merriweather', Georgia, serif; font-size: 1.25rem; font-weight: 700; color: #009AB6; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 3px; }
        .dalo-subtitle { font-size: .76rem; font-weight: 600; color: #64748b; text-transform: uppercase; margin: 0; }
        .dalo-meta { display: flex; justify-content: space-between; margin-top: 16px; font-size: .84rem; font-weight: 500; color: #374151; }

        /* Intro paragraph */
        .dalo-intro { font-size: .77rem; text-align: justify; line-height: 1.75; color: #64748b; margin-bottom: 18px; }
        .dalo-intro strong { color: #1e293b; }
        #intro-district, #intro-street { transition: color .2s; border-bottom: 1px dashed #94a3b8; padding-bottom: 1px; }

        /* Two-column layout */
        .dalo-body { display: grid; grid-template-columns: 7fr 5fr; gap: 24px; }

        /* Section boxes */
        .dalo-section { border: 1px solid #e2e8f0; border-radius: 5px; padding: 12px 14px; background: #f8fafc; margin-bottom: 14px; }
        .dalo-sec-title { color: #009AB6; font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px; }

        /* Inline document fields */
        .dalo-field-row { display: flex; align-items: baseline; gap: 5px; font-size: .81rem; color: #374151; margin-bottom: 7px; flex-wrap: nowrap; }
        .dalo-lbl { flex-shrink: 0; white-space: nowrap; }
        .dalo-input { flex: 1; min-width: 0; border: none; border-bottom: 1px solid #94a3b8; background: transparent; padding: 1px 3px; font-size: .81rem; font-weight: 500; color: #1e3a8a; outline: none; }
        .dalo-input:focus { border-bottom-color: #009AB6; background: rgba(0,154,182,.03); }
        .dalo-input::placeholder { color: #cbd5e1; font-weight: 400; font-style: italic; }
        .dalo-input-xs { max-width: 60px; flex: none; }
        .dalo-select { flex: 1; min-width: 0; border: none; border-bottom: 1px solid #94a3b8; background: transparent; padding: 1px 3px; font-size: .81rem; font-weight: 500; color: #1e3a8a; outline: none; appearance: none; cursor: pointer; }
        .dalo-select:focus { border-bottom-color: #009AB6; }

        /* Block-style fields (Section II) */
        .dalo-fblock { margin-bottom: 9px; font-size: .81rem; }
        .dalo-fblock > label { font-weight: 600; color: #374151; display: block; margin-bottom: 2px; }
        .dalo-full-inp { width: 100%; border: none; border-bottom: 1px solid #94a3b8; background: white; padding: 3px 5px; font-size: .81rem; font-weight: 500; color: #1e3a8a; outline: none; }
        .dalo-full-inp:focus { border-bottom-color: #009AB6; background: rgba(0,154,182,.03); }
        .dalo-full-inp::placeholder { color: #cbd5e1; font-weight: 400; font-style: italic; }
        .dalo-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .dalo-textarea { width: 100%; border: none; border-bottom: 1px solid #94a3b8; background: white; padding: 3px 5px; font-size: .76rem; font-style: italic; color: #1e3a8a; outline: none; resize: none; min-height: 34px; }
        .dalo-textarea:focus { border-bottom-color: #009AB6; }

        /* Section III – applicant signature */
        .dalo-sec-iii { margin-top: 0; }
        .dalo-sec-iii-title { font-size: .81rem; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
        .dalo-sig-row { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 14px; }
        .dalo-sig-blk { display: flex; flex-direction: column; }
        .dalo-sig-hint { font-size: .7rem; color: #94a3b8; margin-bottom: 3px; }
        .dalo-sig-val  { font-size: .83rem; font-weight: 700; color: #1e3a8a; }

        /* Submit button */
        .btn-dalo { width: 100%; padding: 11px; background: #018c87; color: #fff; border: none; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer; transition: all .2s; }
        .btn-dalo:hover { background: #017570; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(1,140,135,.3); }
        .dalo-submit-hint { font-size: .71rem; color: #94a3b8; text-align: center; margin-top: 5px; }

        /* Document footer strip */
        .dalo-doc-foot { margin-top: 20px; padding-top: 10px; border-top: 1px solid #f1f5f9; text-align: center; font-size: .65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .1em; }

        /* Commission right column */
        .dalo-right-col { border-left: 1px solid #e2e8f0; padding-left: 18px; }
        .dalo-comm-title { color: #009AB6; font-weight: 700; font-size: .68rem; text-transform: uppercase; letter-spacing: .12em; text-align: center; margin-bottom: 14px; }
        .dalo-comm-slot { margin-bottom: 13px; }
        .dalo-comm-name { font-weight: 700; font-size: .76rem; color: #475569; margin-bottom: 3px; }
        .dalo-comm-line { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .dalo-imzo-ghost { font-size: .7rem; font-style: italic; color: #94a3b8; }

        /* E-IMZO modal */
        .modal-content { border-radius: 16px; border: none; overflow: hidden; }
        .modal-header { background: #018c87; color: #fff; border-bottom: none; padding: 16px 20px; }
        .modal-title { font-weight: 700; font-size: 1rem; color: #fff; }
        .modal-header .btn-close { filter: invert(1); opacity: .8; }
        .modal-footer { border-top: 1px solid #e8ecf1; padding: 12px 16px; gap: 8px; }

        /* Key cards */
        #eimzo-keys-list { max-height: 370px; overflow-y: auto; border: 1.5px solid #e0e0e0; border-radius: 10px; background: #fafafa; }
        .keys-loader { display: flex; align-items: center; gap: .75rem; padding: 1.2rem; color: #666; font-size: .9rem; }
        .keys-spinner { width: 20px; height: 20px; border: 2px solid #e0e0e0; border-top-color: #018c87; border-radius: 50%; animation: spin .8s linear infinite; flex-shrink: 0; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .keys-empty { padding: 1.2rem; color: #999; font-size: .9rem; text-align: center; }
        .key-card { padding: .85rem 1rem; border-bottom: 1px solid #ececec; cursor: pointer; transition: background .15s; background: white; }
        .key-card:last-child { border-bottom: none; }
        .key-card:hover { background: #f0faf9; }
        .key-card-selected { background: #edfaf9 !important; border-left: 3px solid #018c87; }
        .key-card-expired { opacity: .55; }
        .key-card-name { font-weight: 700; color: #018c87; font-size: .93rem; margin-bottom: .3rem; }
        .key-card-badge { display: inline-block; font-size: .73rem; font-weight: 600; padding: .12rem .55rem; border-radius: 20px; margin-bottom: .35rem; }
        .badge-jismoniy { background: #d4f5e2; color: #1a7a40; }
        .badge-yuridik  { background: #fff0d4; color: #996500; }
        .key-card-stir  { font-size: .78rem; color: #888; margin-bottom: .28rem; }
        .key-card-meta  { margin-top: .28rem; }
        .key-card-row   { display: flex; justify-content: space-between; font-size: .78rem; color: #555; line-height: 1.6; }
        .key-card-row span { color: #888; }
        .key-expired-warn { margin-top: .32rem; font-size: .78rem; color: #c0392b; font-weight: 600; }
        #modal-sign-btn { background: #018c87; border-color: #018c87; }
        #modal-sign-btn:hover:not(:disabled) { background: #017570; border-color: #017570; }
        #modal-sign-btn:disabled { opacity: .45; cursor: not-allowed; }
        #modal-sign-btn.signing { opacity: .7; cursor: wait; }
        /* Full-screen modal on small phones */
        @media (max-width: 480px) {
            .modal-dialog { margin: 0; max-width: 100%; }
            .modal-content { border-radius: 0; min-height: 100dvh; display: flex; flex-direction: column; }
            .modal-body { flex: 1; overflow-y: auto; }
            #eimzo-keys-list { max-height: calc(100dvh - 200px); }
        }

        /* Footer */
        .site-footer { text-align: center; padding: 18px; font-size: .8rem; color: #8a9ab8; }

        /* ─── Responsive ─── */

        /* Tablet: 2-col still, but tighter */
        @media (max-width: 768px) {
            .site-header { padding: 12px 18px; }
            .site-header .brand span { display: none; }
            .dalo-doc { padding: 26px 22px; }
            .dalo-body { gap: 18px; }
            .dalo-right-col { padding-left: 14px; }
            .dalo-2col { gap: 8px; }
        }

        /* Phone: single column, larger touch targets, 16px inputs (prevents iOS zoom) */
        @media (max-width: 560px) {
            .main { padding: 14px 10px 32px; }
            .page-wrap { padding: 0; }

            /* Header */
            .site-header { padding: 11px 14px; }
            .site-header .brand { font-size: .95rem; }
            .site-header .brand span { display: none; }
            .btn-header { padding: 6px 14px; font-size: .82rem; }

            /* Utility bar */
            .util-bar { flex-direction: column; }
            .util-login { border-right: none; border-bottom: 1px solid #f0f2f5; padding: 12px 16px; }
            .util-track { padding: 11px 16px; }
            .util-track-row input { font-size: 1rem; }
            .form-control { font-size: 1rem; }

            /* Document */
            .dalo-doc { padding: 18px 14px 20px; }
            .dalo-emblem { height: 44px; margin-bottom: 8px; }
            .dalo-title { font-size: 1.05rem; }
            .dalo-subtitle { font-size: .68rem; }
            .dalo-meta { flex-direction: column; align-items: center; gap: 2px; font-size: .79rem; margin-top: 10px; }
            .dalo-intro { font-size: .84rem; line-height: 1.65; }

            /* Single column body */
            .dalo-body { grid-template-columns: 1fr; gap: 0; }
            .dalo-right-col { border-left: none; border-top: 1px solid #e2e8f0; padding-left: 0; padding-top: 16px; margin-top: 8px; }

            /* Section boxes */
            .dalo-section { padding: 10px 11px; margin-bottom: 12px; }
            .dalo-sec-title { font-size: .7rem; margin-bottom: 8px; }
            .dalo-2col { grid-template-columns: 1fr; gap: 0; }

            /* Field rows: stack label above input */
            .dalo-field-row { flex-direction: column; align-items: stretch; gap: 3px; margin-bottom: 11px; }
            .dalo-lbl { white-space: normal; font-weight: 600; font-size: .82rem; }

            /* 16px min font on all inputs to prevent iOS auto-zoom */
            .dalo-input       { font-size: 1rem; padding: 6px 4px; min-height: 36px; }
            .dalo-input-xs    { max-width: 100%; }
            .dalo-select      { font-size: 1rem; padding: 6px 4px; min-height: 36px; }
            .dalo-full-inp    { font-size: 1rem; padding: 7px 5px; min-height: 36px; }
            .dalo-textarea    { font-size: 1rem; min-height: 50px; }
            .dalo-fblock label { font-size: .82rem; }

            /* Section III */
            .dalo-sig-row { flex-direction: column; gap: 8px; }
            .dalo-sig-blk:last-child { text-align: left; }
            .btn-dalo { font-size: .95rem; padding: 13px; }

            /* Commission slots */
            .dalo-comm-title { font-size: .7rem; }
            .dalo-comm-name  { font-size: .8rem; }
        }

        /* Very small phones */
        @media (max-width: 380px) {
            .site-header .brand { font-size: .85rem; }
            .dalo-doc { padding: 14px 11px; }
            .dalo-title { font-size: .95rem; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <a href="{{ route('home') }}" class="brand">TUTASH HUDUDLAR <span>— VM 478 asosida</span></a>
    @auth
        <div class="header-user">
            <span class="header-name">{{ auth()->user()->name }}</span>
            <a href="{{ route('dashboard') }}" class="btn-header">Boshqaruv paneli</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn-logout-sm">Chiqish</button>
            </form>
        </div>
    @else
        <a href="{{ route('login') }}" class="btn-header">🔑 Kirish</a>
    @endauth
</header>

<main class="main">
<div class="page-wrap">

    {{-- Compact utility bar: login left | track right --}}
    {{-- <div class="util-bar mb-3">
        <a href="{{ route('login') }}" class="util-login">
            <span class="util-login-icon">🔑</span>
            <span>
                <strong>E-IMZO Kirish</strong>
                <small>Arizalarimni ko'rish</small>
            </span>
        </a>
        <div class="util-track">
            <div class="util-track-lbl">Ariza holatini tekshirish</div>
            @if($errors->has('number'))
                <div class="text-danger" style="font-size:.75rem;margin-bottom:4px">{{ $errors->first('number') }}</div>
            @endif
            <form method="GET" action="{{ route('apply.track.search') }}" class="util-track-row">
                <input type="text" name="number"
                    placeholder="ARZ-2026-0001"
                    value="{{ old('number', request('number')) }}">
                <button type="submit">Tekshirish</button>
            </form>
        </div>
    </div> --}}

    @if($errors->any() && !$errors->has('number'))
    <div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius:12px">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- ═══ Official Shartnoma Application Document (VM 478) ═══ --}}
    <div class="dalo-doc">

        {{-- Document header --}}
        <div class="dalo-doc-head">
            <img src="https://upload.wikimedia.org/wikipedia/commons/7/77/Emblem_of_Uzbekistan.svg" alt="Gerb" class="dalo-emblem">
            <p style="font-size:.76rem; color:#64748b; text-align:center; margin-bottom:6px; line-height:1.5;">
                Toshkent shahar hokimligi huzuridagi<br>
                <strong style="color:#374151;">&laquo;MIRZO ULUG&lsquo;BEK BUSINESS CITY&raquo;
                tadbirkorlik markazini qurish va ekspluatatsiya qilish Direksiyasi&raquo; DUK</strong>ga
            </p>
            <h1 class="dalo-title">ARIZA</h1>
            <p class="dalo-subtitle">Ijara shartnomasi rasmiylashtirilishi uchun &mdash; VM &numero;478</p>
            <div class="dalo-meta">
                <span>Toshkent shahri</span>
                <span>{{ date('Y') }}-yil &laquo;___&raquo; ____________</span>
            </div>
        </div>

        {{-- Applicant info strip --}}
        <div style="border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; background:#f8fafc; margin-bottom:16px;">
            <div style="font-size:.76rem; color:#94a3b8; margin-bottom:3px;">Ariza beruvchi (E-IMZO imzosidan aniqlanadi):</div>
            <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
                <div>
                    <span style="font-size:.78rem; color:#64748b;">FISh / Korxona nomi:</span>&nbsp;
                    <strong id="intro-name" style="font-size:.88rem; color:#1e3a8a;">______________________________</strong>
                </div>
                <div>
                    <span style="font-size:.78rem; color:#64748b;">STIR / PINFL:</span>&nbsp;
                    <strong id="intro-pinfl" style="font-size:.88rem; color:#1e3a8a;">___________________</strong>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('apply') }}" enctype="multipart/form-data" id="apply-form">
            @csrf
            <input type="hidden" name="pkcs7"           id="pkcs7-field">
            <input type="hidden" name="expected_pinfl"  id="expected-pinfl-field">
            <input type="hidden" name="expected_name"   id="expected-name-field">

            <div class="dalo-body">

                {{-- LEFT column --}}
                <div>

                    {{-- Request body with inline fields --}}
                    <div class="dalo-section">
                        <div class="dalo-sec-title">Murojaat matni</div>
                        <p style="font-size:.82rem; color:#374151; line-height:2.2; margin:0;">
                            Vazirlar Mahkamasining &laquo;Tadbirkorlik subyektlari uchun tutash hududlardan
                            mavsumiy foydalanishni tashkil etishni yanada soddalashtirish
                            choratadbirlari to&lsquo;g&lsquo;risida&raquo;gi
                            <strong>2025 yil 31 iyuldagi 478-son</strong> qaroriga asosan

                            <select name="district_id" id="district_id"
                                class="dalo-select @error('district_id') is-invalid @enderror" required>
                                <option value="">&mdash; tuman &mdash;</option>
                                @foreach($districts as $d)
                                <option value="{{ $d->id }}" {{ old('district_id') == $d->id ? 'selected' : '' }}>
                                    {{ $d->name_uz }}
                                </option>
                                @endforeach
                            </select>
                            tumani,
                            <input type="text" name="mahalla" id="mahalla_inp" class="dalo-input"
                                style="max-width:130px" placeholder="MFY nomi"
                                value="{{ old('mahalla') }}">
                            MFY,
                            <input type="text" name="street_name" id="street_inp" class="dalo-input"
                                style="max-width:120px" placeholder="ko&rsquo;cha"
                                value="{{ old('street_name') }}">
                            ko&lsquo;chasi,
                            <input type="text" name="house_number" id="house_inp" class="dalo-input"
                                style="max-width:50px" placeholder="__"
                                value="{{ old('house_number') }}">
                            -uy manzilidagi o&lsquo;zimga tegishli (kadastr raqami
                            <input type="text" name="cadastral_number" id="cadastral_number"
                                class="dalo-input @error('cadastral_number') is-invalid @enderror"
                                style="max-width:155px" placeholder="10:01:00:00:0001"
                                value="{{ old('cadastral_number') }}" required>
                            ) bino&#8209;inshoot (yer uchastkasi)ga tutash
                            <input type="number" name="area_sqm" class="dalo-input dalo-input-xs"
                                placeholder="___" step="0.01" min="0"
                                value="{{ old('area_sqm') }}">
                            kv.&thinsp;m yer uchastkasi hududi bo&lsquo;yicha
                            <strong>ijara shartnomasi rasmiylashtirilishida amaliy yordam
                            berishingizni so&lsquo;raymiz.</strong>
                        </p>
                        @error('district_id')
                            <div class="text-danger" style="font-size:.72rem;margin-top:4px;">{{ $message }}</div>
                        @enderror
                        @error('cadastral_number')
                            <div class="text-danger" style="font-size:.72rem;margin-top:2px;">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Izoh --}}
                    <div class="dalo-section">
                        <div class="dalo-sec-title">Izoh (ixtiyoriy)</div>
                        <textarea name="description" class="dalo-textarea" rows="2"
                            placeholder="Qo'shimcha ma'lumot...">{{ old('description') }}</textarea>
                    </div>

                    {{-- File upload --}}
                    <div class="dalo-section" style="background:#fff">
                        <div class="dalo-sec-title">Hujjat biriktirish (ixtiyoriy)</div>
                        <p style="font-size:.74rem;color:#94a3b8;margin-bottom:8px">
                            Mulk hujjati, kadastr ko'chirma yoki boshqa hujjatlar (PDF, DOC, maks. 10 MB)
                        </p>
                        <input type="file" name="documents[]" class="form-control form-control-sm"
                            multiple accept=".pdf,.doc,.docx">
                    </div>

                    {{-- Signature --}}
                    <div class="dalo-sec-iii">
                        <div class="dalo-sec-iii-title">Ariza beruvchi imzosi</div>
                        <div class="dalo-sig-row">
                            <div class="dalo-sig-blk">
                                <span class="dalo-sig-hint">(imzo)</span>
                                <span class="dalo-sig-val">______________________</span>
                            </div>
                            <div class="dalo-sig-blk" style="text-align:right">
                                <span class="dalo-sig-hint">__ _____ 20__ yil</span>
                                <span class="dalo-sig-val" id="signer-name-val">______________________</span>
                            </div>
                        </div>
                        <button type="button" class="btn-dalo" onclick="openSignModal()">
                            &#128273; Ariza yuborish (E-IMZO bilan imzolash)
                        </button>
                        <div class="dalo-submit-hint">Ariza yuborishdan oldin elektron imzo bilan tasdiqlanadi</div>
                    </div>

                </div>{{-- /left --}}

                {{-- RIGHT column — workflow steps --}}
                <div class="dalo-right-col">
                    <div class="dalo-comm-title">Ko&rsquo;rib chiqish tartibi</div>

                    @php
                    $wfSteps = [
                        ['num'=>1, 'role'=>'Devon',           'desc'=>'Ariza qabul qilinadi'],
                        ['num'=>2, 'role'=>'Ijrochi',         'desc'=>"Rahbarga yo'naltiradi (+/&minus;)"],
                        ['num'=>3, 'role'=>'Rahbar',          'desc'=>'Topshiriq beradi'],
                        ['num'=>4, 'role'=>'Tuman Vakili',    'desc'=>"Tutash hududga yo'naltiradi (+/&minus;)"],
                        ['num'=>5, 'role'=>'Yurist',          'desc'=>'Huquqiy ekspertiza'],
                        ['num'=>6, 'role'=>'Komplayans',      'desc'=>'Muvofiqlik tekshiruvi'],
                        ['num'=>7, 'role'=>'Rahbar',          'desc'=>'Yakuniy tasdiq ✓'],
                    ];
                    @endphp

                    @foreach($wfSteps as $step)
                    <div class="dalo-comm-slot">
                        <div style="display:flex; align-items:flex-start; gap:8px;">
                            <span style="background:#018c87; color:#fff; border-radius:50%;
                                min-width:20px; height:20px; display:inline-flex;
                                align-items:center; justify-content:center;
                                font-size:.68rem; font-weight:700; flex-shrink:0; margin-top:1px;">
                                {{ $step['num'] }}
                            </span>
                            <div>
                                <div style="font-weight:700; font-size:.78rem; color:#1e293b;">{{ $step['role'] }}</div>
                                <div style="font-size:.72rem; color:#64748b;">{!! $step['desc'] !!}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    <div style="margin-top:14px; padding-top:12px; border-top:2px solid #e2e8f0;
                        text-align:center; font-size:.78rem; font-weight:700; color:#018c87;">
                        &#8595;&nbsp; Ijara shartnomasi tuziladi
                    </div>

                    <div style="margin-top:14px; padding:10px 12px; background:#f0faf9;
                        border-radius:8px; border:1px solid #b2dfdb; font-size:.72rem; color:#374151;">
                        <strong>Eslatma:</strong> 3-bosqichda ijrochi arizani
                        rad etsa, <em>javob xat</em> yuboriladi.
                        Barcha bosqichlardan o&lsquo;tgach
                        <strong>ijara shartnomasi</strong> tuziladi.
                    </div>
                </div>

            </div>{{-- /dalo-body --}}
        </form>

        <div class="dalo-doc-foot">
            Elektron tizim orqali shakllantirildi &mdash; Tutash Hudud &nbsp;&middot;&nbsp; VM &numero;478
        </div>
    </div>




</div>
</main>

{{-- E-IMZO signing modal --}}
<div class="modal fade" id="eimzo-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">ERI ni tanlang</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="eimzo-status" style="margin-bottom:8px"></div>
                <div id="eimzo-message"></div>
                <div id="eimzo-progress"></div>
                <div id="eimzo-keys-list">
                    <div class="keys-loader">
                        <div class="keys-spinner"></div>
                        <span>E-IMZO bilan ulanilmoqda...</span>
                    </div>
                </div>
                <div id="modal-sign-error" class="text-danger" style="font-size:.82rem;margin-top:8px;display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button"
                    style="background:transparent;border:1.5px solid #e0e0e0;color:#666;padding:9px 20px;border-radius:10px;font-size:.93rem;cursor:pointer"
                    data-bs-dismiss="modal">
                    Bekor qilish
                </button>
                <button type="button" id="modal-sign-btn" class="btn btn-primary px-4"
                    onclick="signAndSubmit()" disabled>
                    🔑 Imzolash va yuborish
                </button>
            </div>
        </div>
    </div>
</div>

<footer class="site-footer">
    © {{ date('Y') }} Qo'shni hudud tizimi &nbsp;·&nbsp; Vazirlar Mahkamasi Qarori №478 asosida
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/e-imzo.js') }}"></script>
<script src="{{ asset('js/e-imzo-client.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script>
// Enable sign button when a key card is selected
document.addEventListener('click', function(e) {
    if (e.target.closest('.key-card')) {
        document.getElementById('modal-sign-btn').disabled = false;
    }
});

// Validate required fields → open E-IMZO modal
function openSignModal() {
    var district  = document.getElementById('district_id').value;
    var cadastral = document.getElementById('cadastral_number').value.trim();
    if (!district) {
        document.getElementById('district_id').focus();
        document.getElementById('district_id').classList.add('is-invalid');
        return;
    }
    if (!cadastral) {
        document.getElementById('cadastral_number').focus();
        document.getElementById('cadastral_number').classList.add('is-invalid');
        return;
    }
    document.getElementById('pkcs7-field').value = '';
    document.getElementById('modal-sign-btn').disabled = true;
    var errEl = document.getElementById('modal-sign-error');
    if (errEl) errEl.style.display = 'none';
    new bootstrap.Modal(document.getElementById('eimzo-modal')).show();
}

// Cadastral number auto-formatting  (e.g. 10:01:00:00:0001)
(function() {
    var inp = document.getElementById('cadastral_number');
    if (!inp) return;
    inp.addEventListener('input', function(e) {
        var raw = e.target.value.replace(/:/g, '');
        var parts = [];
        var rest  = raw;
        for (var i = 0; i < 5 && rest.length > 0; i++) {
            parts.push(rest.substring(0, 2));
            rest = rest.substring(2);
        }
        if (rest.length > 0) parts.push(rest);
        e.target.value = parts.join(':');
    });
})();

// Clear invalid state on change
document.getElementById('district_id').addEventListener('change', function() {
    this.classList.remove('is-invalid');
    var introEl = document.getElementById('intro-district');
    if (introEl) {
        var txt = this.options[this.selectedIndex];
        introEl.textContent = (txt && txt.value) ? txt.text : '__________________';
        introEl.style.color = (txt && txt.value) ? '#1e3a8a' : '';
    }
});
document.getElementById('cadastral_number').addEventListener('input', function() { this.classList.remove('is-invalid'); });

// Live-update intro paragraph blanks
document.querySelector('[name="street_name"]').addEventListener('input', function() {
    var introEl = document.getElementById('intro-street');
    if (introEl) {
        introEl.textContent = this.value.trim() || '__________________';
        introEl.style.color = this.value.trim() ? '#1e3a8a' : '';
    }
});

// Sign with E-IMZO then submit form
function signAndSubmit() {
    if (typeof selectedCardVo === 'undefined' || !selectedCardVo) {
        alert('Iltimos, kalitni tanlang');
        return;
    }
    var vo = selectedCardVo;
    if (vo.expired) { alert("Bu kalitning muddati tugagan. Boshqa kalit tanlang."); return; }

    var cadastral = document.getElementById('cadastral_number').value.trim();
    var distEl    = document.getElementById('district_id');
    var distText  = distEl && distEl.selectedIndex >= 0 ? (distEl.options[distEl.selectedIndex].text || '') : '';
    var dataToSign = 'ARIZA|' + cadastral + '|' + distText + '|' + new Date().toISOString();

    var btn = document.getElementById('modal-sign-btn');
    var errEl = document.getElementById('modal-sign-error');
    btn.disabled = true; btn.classList.add('signing'); btn.textContent = 'Kalit yuklanmoqda...';
    if (errEl) errEl.style.display = 'none';

    EIMZOClient.loadKey(vo, function(keyId) {
        btn.textContent = 'Imzolanmoqda...';
        EIMZOClient.createPkcs7(keyId, dataToSign, null, function(pkcs7) {
            document.getElementById('pkcs7-field').value         = pkcs7;
            document.getElementById('expected-pinfl-field').value = vo.PINFL || vo.UID || '';
            document.getElementById('expected-name-field').value  = vo.CN || '';
            var modal = bootstrap.Modal.getInstance(document.getElementById('eimzo-modal'));
            if (modal) {
                document.getElementById('eimzo-modal').addEventListener('hidden.bs.modal', function h() {
                    document.getElementById('eimzo-modal').removeEventListener('hidden.bs.modal', h);
                    document.getElementById('apply-form').submit();
                });
                modal.hide();
            } else {
                document.getElementById('apply-form').submit();
            }
        }, function(err) {
            btn.disabled = false; btn.classList.remove('signing'); btn.innerHTML = '🔑 Imzolash va yuborish';
            if (errEl) { errEl.textContent = 'Imzolashda xatolik: ' + (err || 'nomalum'); errEl.style.display = 'block'; }
        }, false);
    }, function(err) {
        btn.disabled = false; btn.classList.remove('signing'); btn.innerHTML = '🔑 Imzolash va yuborish';
        if (errEl) { errEl.textContent = 'Kalit yuklanmadi: ' + (err || 'nomalum'); errEl.style.display = 'block'; }
    }, true);
}
</script>
</body>
</html>
