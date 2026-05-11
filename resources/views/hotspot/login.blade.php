<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f172a">
    <title>Feralix Hotspot — Login</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f172a;
            --surface:   #1e293b;
            --border:    #334155;
            --accent:    #38bdf8;
            --accent-dk: #0284c7;
            --text:      #f1f5f9;
            --muted:     #94a3b8;
            --error-bg:  #450a0a;
            --error:     #fca5a5;
            --success-bg:#052e16;
            --success:   #86efac;
            --radius:    14px;
        }

        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -20%, rgba(56,189,248,.15) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(99,102,241,.10) 0%, transparent 50%);
        }

        /* ── Layout ── */
        .page {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px 40px;
            gap: 24px;
        }

        /* ── Brand ── */
        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-align: center;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dk));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 32px rgba(56,189,248,.35);
        }

        .brand-icon svg { width: 28px; height: 28px; color: #fff; }

        .brand-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: var(--text);
        }

        .brand-sub {
            font-size: 13px;
            color: var(--muted);
            letter-spacing: 0.2px;
        }

        /* ── Card ── */
        .card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,.4);
        }

        .card-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .card-desc {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* ── Alert ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .alert-error  { background: var(--error-bg);   color: var(--error);   border: 1px solid rgba(252,165,165,.2); }
        .alert-info   { background: #0c1a2e;            color: var(--accent);  border: 1px solid rgba(56,189,248,.2); }
        .alert svg    { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }

        /* ── Form ── */
        .form { display: flex; flex-direction: column; gap: 16px; }

        .field { display: flex; flex-direction: column; gap: 6px; }

        label {
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            letter-spacing: 0.1px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 13px;
            width: 17px;
            height: 17px;
            color: var(--muted);
            pointer-events: none;
            flex-shrink: 0;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 11px 14px 11px 40px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 15px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            -webkit-appearance: none;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(56,189,248,.15);
        }

        input::placeholder { color: #475569; }

        /* password toggle */
        .toggle-pw {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 4px;
            display: flex;
            align-items: center;
        }

        .toggle-pw svg { width: 18px; height: 18px; }
        .toggle-pw:hover { color: var(--text); }

        /* ── Submit Button ── */
        .btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dk));
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.2px;
            transition: opacity .15s, transform .1s;
            margin-top: 4px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover  { opacity: .92; }
        .btn:active { transform: scale(.98); }

        .btn-loader { display: none; }
        .btn.loading .btn-text   { display: none; }
        .btn.loading .btn-loader { display: flex; align-items: center; gap: 8px; }

        .spinner {
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Info Strip ── */
        .info-strip {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--muted);
        }

        .info-item svg { width: 13px; height: 13px; color: var(--accent); }

        /* ── Footer ── */
        footer {
            text-align: center;
            font-size: 12px;
            color: #334155;
            padding: 16px;
        }

        /* ── Responsive ── */
        @media (max-width: 440px) {
            .card { padding: 22px 18px; border-radius: 12px; }
        }
    </style>
</head>
<body>

<main class="page">

    {{-- Brand --}}
    <div class="brand">
        <div class="brand-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01
                       m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0
                       M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
            </svg>
        </div>
        <div>
            <div class="brand-name">Feralix Hotspot</div>
            <div class="brand-sub">
                @if($identity)
                    Titik akses: <strong style="color:var(--accent)">{{ $identity }}</strong>
                @else
                    Akses internet berkecepatan tinggi
                @endif
            </div>
        </div>
    </div>

    {{-- Card --}}
    <div class="card">
        <div class="card-title">Masuk dengan Voucher</div>
        <div class="card-desc">Gunakan username dan password dari voucher hotspot Anda.</div>

        {{-- Error Alert --}}
        @if($errorMessage)
        <div class="alert alert-error" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
            <span>{{ $errorMessage }}</span>
        </div>
        @endif

        {{-- No link-login fallback --}}
        @if(!$linkLogin)
        <div class="alert alert-info" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.94 6.94a.75.75 0 11-1.061-1.061 3.5 3.5 0 115.09 4.807c-.329.28-.609.521-.757.701-.07.085-.098.14-.105.163L12 11.757v.243a.75.75 0 01-1.5 0v-.5c0-.55.282-.995.61-1.393.232-.278.556-.554.867-.812a2 2 0 10-3.037-2.344z" clip-rule="evenodd"/>
            </svg>
            <span>Buka halaman ini dari jaringan hotspot aktif, bukan langsung via browser.</span>
        </div>
        @endif

        {{-- Login Form — submits to MikroTik link-login URL --}}
        <form class="form" id="loginForm"
              action="{{ $linkLogin ?: '#' }}"
              method="POST"
              onsubmit="handleSubmit(event)">

            <input type="hidden" name="dst" value="{{ $linkOrig }}">

            {{-- Username --}}
            <div class="field">
                <label for="username">Username Voucher</label>
                <div class="input-wrap">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                    <input type="text"
                           id="username"
                           name="username"
                           value="{{ $username }}"
                           placeholder="Masukkan username"
                           autocomplete="username"
                           autocapitalize="off"
                           autocorrect="off"
                           spellcheck="false"
                           required>
                </div>
            </div>

            {{-- Password --}}
            <div class="field">
                <label for="password">Password Voucher</label>
                <div class="input-wrap">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Masukkan password"
                           autocomplete="current-password"
                           required>
                    <button type="button" class="toggle-pw" onclick="togglePassword()" aria-label="Tampilkan password">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Submit --}}
            <button type="submit" class="btn" id="submitBtn">
                <span class="btn-text">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                    </svg>
                    Masuk ke Internet
                </span>
                <span class="btn-loader">
                    <span class="spinner"></span>
                    Sedang masuk...
                </span>
            </button>

        </form>
    </div>

    {{-- Info Strip --}}
    <div class="info-strip">
        <div class="info-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
            </svg>
            Koneksi terenkripsi
        </div>
        <div class="info-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/>
            </svg>
            Voucher berlaku sesuai paket
        </div>
        @if($mac)
        <div class="info-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M8 16.25a.75.75 0 01.75-.75h2.5a.75.75 0 010 1.5h-2.5a.75.75 0 01-.75-.75z"/>
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0h8v12H6V4z" clip-rule="evenodd"/>
            </svg>
            {{ $mac }}
        </div>
        @endif
    </div>

</main>

<footer>
    &copy; {{ date('Y') }} Feralix Billing System
</footer>

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eyeIcon');
        const show  = input.type === 'password';

        input.type = show ? 'text' : 'password';
        icon.innerHTML = show
            ? `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>`
            : `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>`;
    }

    function handleSubmit(e) {
        const action = document.getElementById('loginForm').action;
        if (!action || action === '#' || action === window.location.href) {
            e.preventDefault();
            return;
        }
        document.getElementById('submitBtn').classList.add('loading');
    }

    // Auto-focus username jika kosong
    window.addEventListener('DOMContentLoaded', () => {
        const u = document.getElementById('username');
        if (!u.value) u.focus();
    });
</script>

</body>
</html>
