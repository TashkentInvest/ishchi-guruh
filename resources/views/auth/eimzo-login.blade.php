<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Кириш — ТОШКЕНТ ШАҲРИНИ РИВОЖЛАНТИРИШ ЖАМҒАРМАСИ</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #e7f0f1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Top bar ── */
        .auth-topbar {
            background: #018c87;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(1,140,135,.25);
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }

        .auth-brand-icon {
            width: 38px; height: 38px;
            border-radius: 9px;
            background: rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 900;
        }

        .auth-brand-text {
            display: flex;
            flex-direction: column;
        }

        .auth-brand-title {
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .auth-brand-sub {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 1px;
        }

        .auth-topbar-btn {
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.3);
            color: #fff;
            padding: 7px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        .auth-topbar-btn:hover { background: rgba(255,255,255,.25); color: #fff; }

        /* ── Main ── */
        .auth-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        /* ── Card ── */
        .auth-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 12px 40px rgba(0,0,0,.10);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
        }

        .auth-card-header {
            padding: 28px 28px 20px;
            text-align: center;
            border-bottom: 1px solid #f0f2f5;
        }

        .auth-card-logo {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #018c87, #00bfaf);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; font-weight: 900; color: #fff;
            margin: 0 auto 14px;
            box-shadow: 0 6px 18px rgba(1,140,135,.3);
        }

        .auth-card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #15191e;
            margin-bottom: 4px;
        }

        .auth-card-sub {
            font-size: 0.82rem;
            color: #6e788b;
        }

        .auth-card-body { padding: 24px 28px; }

        /* ── Tabs ── */
        .auth-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            padding: 4px;
            background: #f4f6f8;
            border-radius: 10px;
        }

        .auth-tab {
            flex: 1;
            padding: 9px 6px;
            border: none;
            background: transparent;
            color: #6e788b;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .auth-tab.active {
            background: #fff;
            color: #018c87;
            font-weight: 700;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }

        .auth-tab-content { display: none; }
        .auth-tab-content.active { display: block; }

        /* ── Key list ── */
        .keys-list-label {
            display: block;
            color: #15191e;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        #eimzo-keys-list {
            max-height: 320px;
            overflow-y: auto;
            border: 1.5px solid #e0e5ea;
            border-radius: 10px;
            background: #fafafa;
            margin-bottom: 16px;
        }

        .keys-loader {
            display: flex; align-items: center; gap: 10px;
            padding: 16px; color: #6e788b; font-size: 0.875rem;
        }

        .keys-spinner {
            width: 20px; height: 20px;
            border: 2px solid #e0e5ea;
            border-top-color: #018c87;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            flex-shrink: 0;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .keys-empty {
            padding: 20px; color: #aab0bb;
            font-size: 0.875rem; text-align: center;
        }

        .key-card {
            padding: 12px 14px;
            border-bottom: 1px solid #ececec;
            cursor: pointer;
            transition: background 0.15s;
            background: #fff;
        }
        .key-card:last-child { border-bottom: none; }
        .key-card:hover { background: #f0faf9; }

        .key-card-selected {
            background: #edfaf9 !important;
            border-left: 3px solid #018c87;
        }
        .key-card-expired { opacity: 0.5; }

        .key-card-name {
            font-weight: 700;
            color: #018c87;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .key-card-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            margin-bottom: 4px;
        }
        .badge-jismoniy { background: #d4f5e2; color: #1a7a40; }
        .badge-yuridik  { background: #fff0d4; color: #996500; }

        .key-card-stir  { font-size: 0.78rem; color: #aab0bb; margin-bottom: 3px; }
        .key-card-meta  { margin-top: 4px; }
        .key-card-row   { display: flex; justify-content: space-between; font-size: 0.78rem; color: #6e788b; line-height: 1.6; }
        .key-card-row span { color: #aab0bb; }
        .key-expired-warn { margin-top: 4px; font-size: 0.78rem; color: #e63260; font-weight: 600; }

        /* ── Status messages ── */
        #eimzo-status, #eimzo-message, #eimzo-progress { margin-bottom: 8px; }
        .status-loading { background: #fff3cd; color: #856404; border: 1px solid #ffe09a; border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; }
        .status-success { background: #d4f5e2; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; }
        .status-error   { background: #fde2e8; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; }
        .status-info    { background: #e0f7f6; color: #0c5460; border: 1px solid #b8e8e6; border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; }

        /* ── Buttons ── */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #018c87;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 10px;
        }
        .btn-login:hover:not(:disabled) { background: #017570; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(1,140,135,.3); }
        .btn-login:disabled { opacity: 0.45; cursor: not-allowed; }

        .btn-usb {
            width: 100%;
            padding: 11px 14px;
            background: transparent;
            color: #6e788b;
            border: 1.5px solid #e0e5ea;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-usb:hover { border-color: #018c87; color: #018c87; background: rgba(1,140,135,.03); }
        .usb-icon { width: 26px; height: 26px; }

        /* ── Footer ── */
        .auth-card-footer {
            padding: 16px 28px 24px;
            border-top: 1px solid #f0f2f5;
            text-align: center;
        }
        .auth-footer-text { font-size: 0.82rem; color: #6e788b; margin-bottom: 6px; }
        .auth-footer-link { color: #018c87; text-decoration: none; font-weight: 600; font-size: 0.88rem; }
        .auth-footer-link:hover { text-decoration: underline; }
        .auth-help { font-size: 0.78rem; color: #aab0bb; margin-top: 12px; line-height: 1.5; }
        .auth-help a { color: #018c87; text-decoration: none; }

        /* ── Error from server ── */
        .server-error {
            background: #fde2e8; color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.875rem;
            margin-bottom: 16px;
        }

        @media (max-width: 540px) {
            .auth-card { border-radius: 16px; }
            .auth-card-body { padding: 20px 20px; }
            .auth-card-footer { padding: 14px 20px 20px; }
        }
    </style>
</head>
<body>

    {{-- Top bar --}}
    <div class="auth-topbar">
        <a href="{{ route('login') }}" class="auth-brand">
            <div class="auth-brand-icon">T</div>
            <div class="auth-brand-text">
                <span class="auth-brand-title">ТОШКЕНТ ЖАМҒАРМАСИ</span>
                <span class="auth-brand-sub">Маблағлар реестри</span>
            </div>
        </a>
        <a href="{{ route('login') }}" class="auth-topbar-btn">← Bosh sahifaga</a>
    </div>

    {{-- Main --}}
    <main class="auth-main">
        <div class="auth-card">

            {{-- Card header --}}
            <div class="auth-card-header">
                <div class="auth-card-logo">T</div>
                <div class="auth-card-title">Тизимга кириш</div>
                <div class="auth-card-sub">Шахсий кабинетингизга киринг</div>
            </div>

            {{-- Card body --}}
            <div class="auth-card-body">

                {{-- Server errors --}}
                @if($errors->any())
                <div class="server-error">
                    @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                    @endforeach
                </div>
                @endif

                {{-- Method tabs --}}
                <div class="auth-tabs">
                    <button class="auth-tab {{ $errors->has('password_login') ? 'active' : '' }}" id="tab-btn-login" onclick="switchTab('login', this)">Parol</button>
                    <button class="auth-tab {{ !$errors->has('password_login') ? 'active' : '' }}" id="tab-btn-eri" onclick="switchTab('eri', this)">ERI</button>
                </div>

                {{-- Status messages --}}
                <div id="eimzo-status"></div>
                <div id="eimzo-message"></div>
                <div id="eimzo-progress"></div>

                {{-- ERI tab (default active) --}}
                <div id="tab-eri" class="auth-tab-content {{ !$errors->has('password_login') ? 'active' : '' }}">
                    <label class="keys-list-label">ERI ni tanlang</label>
                    <div id="eimzo-keys-list">
                        <div class="keys-loader">
                            <div class="keys-spinner"></div>
                            <span>E-IMZO bilan ulanilmoqda...</span>
                        </div>
                    </div>

                    <button type="button" class="btn-login" id="login-btn" onclick="eimzoLogin()" disabled>
                        Kirish
                    </button>

                    <button type="button" class="btn-usb" onclick="AppLoad()">
                        <svg class="usb-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M38.701 24.355H36.512L36.045 26.62H38.555C38.788 26.62 40.1 26.453 40.1 25.353C40.1 24.266 38.701 24.355 38.701 24.355ZM39.521 20.339H37.371L36.997 22.135H39.334C39.522 22.135 40.447 21.989 40.447 21.129C40.447 20.271 39.521 20.339 39.521 20.339ZM44.064 23.109C44.064 23.109 45.5 22.366 45.5 20.016C45.5 16.301 41.123 16.5 41.123 16.5H38.258L38.932 13.5H17.961C8.617 13.5 5.803 20.274 5.803 20.274L5.736 20.5H2.547L1.5 26.5H4.87L4.871 26.643C4.871 26.643 4.586 33.5 15.334 33.5H34.376L35.055 30.5H39.444C44.173 30.5 45.035 27.146 45.035 25.6C45.032 23.838 44.064 23.109 44.064 23.109Z" fill="#44444bba"/>
                            <path d="M14.022 29.5C8.716 29.5 8.716 25.876 8.784 25.514C9.37459 22.8414 9.97093 20.17 10.573 17.5H14.413L13.055 23.854C13.055 23.854 12.084 26.582 14.306 26.582C16.387 26.582 16.642 24.047 16.642 24.047L18.107 17.504H21.946L20.364 24.483C20.365 24.48 20.258 29.5 14.022 29.5ZM26.098 29.521C23.424 29.521 21.14 28.259 21.242 25.381H24.68C24.68 25.957 24.766 27.008 26.313 27.008C26.94 27.008 28.001 26.742 28.001 25.875C28.001 24.244 22.404 25.09 22.404 21.305C22.404 19.242 24.303 17.52 27.393 17.52C32.369 17.52 32.006 21.269 32.006 21.269H28.637C28.637 20.225 27.973 20.065 27.174 20.065C26.374 20.065 25.802 20.408 25.802 21.009C25.802 22.48 31.436 21.465 31.436 25.54C31.436 27.305 30.012 29.521 26.098 29.521Z" fill="white"/>
                        </svg>
                        USB token orqali kirish
                    </button>
                </div>

                {{-- Login tab — real email/password form --}}
                <div id="tab-login" class="auth-tab-content {{ $errors->has('password_login') ? 'active' : '' }}">
                    @if($errors->has('password_login'))
                    <div style="background:#fde2e8;color:#721c24;border:1px solid #f5c6cb;border-radius:10px;padding:12px 16px;font-size:.875rem;margin-bottom:14px;">
                        {{ $errors->first('password_login') }}
                    </div>
                    @endif
                    <form method="POST" action="{{ route('login.password') }}">
                        @csrf
                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:.85rem;font-weight:600;color:#15191e;margin-bottom:6px;">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                style="width:100%;padding:10px 14px;border:1.5px solid #dde3ee;border-radius:10px;font-size:.9rem;color:#2c3e60;outline:none;"
                                placeholder="email@tutash.uz" required autocomplete="email">
                        </div>
                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-size:.85rem;font-weight:600;color:#15191e;margin-bottom:6px;">Parol</label>
                            <input type="password" name="password"
                                style="width:100%;padding:10px 14px;border:1.5px solid #dde3ee;border-radius:10px;font-size:.9rem;color:#2c3e60;outline:none;"
                                placeholder="••••••••" required autocomplete="current-password">
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;font-size:.82rem;color:#6e788b;">
                            <input type="checkbox" name="remember" id="remember-me" style="accent-color:#018c87;">
                            <label for="remember-me">Eslab qolish</label>
                        </div>
                        <button type="submit"
                            style="width:100%;padding:13px;background:#018c87;color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;">
                            Kirish
                        </button>
                    </form>
                    <div style="margin-top:16px;padding:12px 14px;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;font-size:.78rem;color:#166534;">
                        <strong>Komissiya a'zolari uchun kirish ma'lumotlari:</strong><br>
                        Email: <em>kadastr@tutash.uz</em> va boshqalar<br>
                        Parol: <em>commission123</em> (standart)
                    </div>
                </div>


            </div>

            {{-- Card footer --}}
            <div class="auth-card-footer">
                <p class="auth-footer-text">Тизимга кириш учун электрон имзодан фойдаланинг</p>
                <a href="{{ route('login') }}" class="auth-footer-link">Кириш саҳифасига Щид →</a>
                <div class="auth-help">
                    <p>E-IMZO dasturi o'rnatilgan bo'lishi kerak</p>
                    <a href="https://e-imzo.uz/main/downloads/" target="_blank">E-IMZO yuklab olish</a>
                    &nbsp;·&nbsp;
                    <a href="#" onclick="AppLoad(); return false">Qayta ulanish</a>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('js/e-imzo.js') }}"></script>
    <script src="{{ asset('js/e-imzo-client.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    <script>
        function switchTab(name, el) {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-tab-content').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            var content = document.getElementById('tab-' + name);
            if (content) content.classList.add('active');
        }
    </script>
</body>
</html>
