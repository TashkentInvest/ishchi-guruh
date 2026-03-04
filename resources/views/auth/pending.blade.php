<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasdiqlash kutilmoqda — ТОШКЕНТ ЖАМҒАРМАСИ</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #e7f0f1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 12px 40px rgba(0,0,0,.10);
            width: 100%;
            max-width: 480px;
            padding: 48px 40px;
            text-align: center;
        }
        .icon-wrap {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #fff3cd, #ffe09a);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.4rem;
            margin: 0 auto 24px;
            box-shadow: 0 4px 16px rgba(255,193,7,.25);
        }
        h1 { font-size: 1.3rem; font-weight: 700; color: #15191e; margin-bottom: 12px; }
        .sub { font-size: 0.9rem; color: #6e788b; line-height: 1.6; margin-bottom: 28px; }
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 28px;
            text-align: left;
        }
        .info-row { display: flex; gap: 10px; align-items: flex-start; font-size: 0.83rem; color: #374151; margin-bottom: 8px; }
        .info-row:last-child { margin-bottom: 0; }
        .info-row strong { color: #6e788b; min-width: 70px; flex-shrink: 0; }
        .btn-logout {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 28px;
            background: transparent;
            border: 1.5px solid #e0e5ea;
            color: #6e788b;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-logout:hover { border-color: #018c87; color: #018c87; }
        .dots { display: inline-flex; gap: 5px; margin-top: 20px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #018c87; animation: bounce 1.2s ease-in-out infinite; }
        .dot:nth-child(2) { animation-delay: 0.2s; }
        .dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce { 0%,80%,100% { transform: scale(0.6); opacity: 0.4; } 40% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">⏳</div>
        <h1>Tasdiqlash kutilmoqda</h1>
        <p class="sub">
            Siz muvaffaqiyatli ro'yxatdan o'tdingiz. Tizimdan foydalanish uchun
            administrator tasdig'i kerak. Iltimos, biroz kuting.
        </p>

        <div class="info-box">
            <div class="info-row">
                <strong>Ism:</strong>
                <span>{{ auth()->user()->name }}</span>
            </div>
            @if(auth()->user()->pinfl)
            <div class="info-row">
                <strong>PINFL:</strong>
                <span style="font-family:monospace">{{ auth()->user()->pinfl }}</span>
            </div>
            @endif
            @if(auth()->user()->organization)
            <div class="info-row">
                <strong>Tashkilot:</strong>
                <span>{{ auth()->user()->organization }}</span>
            </div>
            @endif
            <div class="info-row">
                <strong>Holat:</strong>
                <span style="color:#d97706;font-weight:600;">⏳ Ko'rib chiqilmoqda</span>
            </div>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-logout">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Chiqish
            </button>
        </form>

        <div class="dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>
</body>
</html>
