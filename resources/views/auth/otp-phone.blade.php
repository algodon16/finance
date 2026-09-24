<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify via Phone - Bestlink College of the Philippines</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #ffffff; }
        .login-page { min-height: 100vh; min-height: 100dvh; width: 100%; display: flex; align-items: center; justify-content: center; padding: 20px; background: #ffffff; }
        .login-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 12px 32px rgba(15, 42, 90, 0.12); width: 100%; max-width: 440px; padding: 36px 32px 32px; }
        .login-logo-wrap { text-align: center; margin-bottom: 16px; }
        .login-logo { width: 72px; height: 72px; object-fit: contain; display: inline-block; }
        .login-title { margin: 0 0 4px; text-align: center; font-size: 1.1rem; font-weight: 700; color: #0f2a5a; }
        .login-subtitle { margin: 0 0 20px; text-align: center; font-size: 0.85rem; color: #64748b; }
        .otp-heading { margin: 0 0 6px; text-align: center; font-size: 1.15rem; font-weight: 700; color: #0f2a5a; }
        .otp-desc { margin: 0 0 4px; text-align: center; font-size: 0.875rem; color: #475569; line-height: 1.6; }
        .otp-masked { text-align: center; font-weight: 700; color: #0f2a5a; margin-bottom: 18px; font-size: 0.95rem; }
        .login-alert { background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 6px; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 18px; line-height: 1.6; }
        .login-status { background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; border-radius: 6px; padding: 10px 14px; font-size: 0.85rem; margin-bottom: 18px; line-height: 1.6; }
        .otp-boxes { display: flex; gap: 8px; justify-content: center; margin: 6px 0 18px; }
        .otp-box { width: 48px; height: 54px; text-align: center; font-size: 1.4rem; font-weight: 700; color: #0f2a5a; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
        .otp-box:focus { border-color: #6d28d9; box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.15); }
        .otp-timer { text-align: center; font-size: 0.85rem; color: #475569; margin-bottom: 18px; }
        .otp-timer strong { color: #0f2a5a; }
        .otp-links { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; text-align: center; font-size: 0.85rem; }
        .login-link { color: var(--primary); font-weight: 600; text-decoration: none; background: none; border: none; cursor: pointer; font-size: 0.85rem; padding: 0; }
        .login-link:hover { text-decoration: underline; }
        .login-link:disabled { opacity: 0.5; cursor: not-allowed; }
        @media (max-width: 480px) { .login-card { padding: 28px 22px 26px; } .otp-box { width: 42px; height: 50px; } }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo-wrap">
                <img src="{{ asset('images/bcp-logo.png') }}" alt="Bestlink College logo" class="login-logo" width="72" height="72" onerror="this.style.display='none'">
            </div>
            <h1 class="login-title">BESTLINK COLLEGE OF THE PHILIPPINES</h1>

            <h2 class="otp-heading">Verify via Phone</h2>
            <p class="otp-desc">A verification code has been sent to your registered phone number.</p>
            <p class="otp-masked">{{ $maskedPhone }}</p>

            @if (session('status'))
                <div class="login-status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="login-alert">
                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span><br>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('otp.phone.verify') }}" id="otpForm">
                @csrf
                <div class="otp-boxes" id="otpBoxes">
                    @for ($i = 0; $i < 6; $i++)
                        <input type="text" class="otp-box" inputmode="numeric" autocomplete="one-time-code" maxlength="1" aria-label="Digit {{ $i + 1 }}">
                    @endfor
                </div>
                <input type="hidden" name="code" id="otpCode">
                <div class="otp-timer">OTP expires in <strong id="otpCountdown">05:00</strong></div>
                <x-ui.button type="submit" block>Verify OTP</x-ui.button>
            </form>

            <div class="otp-links">
                <form method="POST" action="{{ route('otp.phone.resend') }}">
                    @csrf
                    <button type="submit" class="login-link" id="resendBtn">Resend OTP</button>
                    <span id="resendCooldown" style="color:#64748b;"></span>
                </form>
                <form method="POST" action="{{ route('otp.back-email') }}">
                    @csrf
                    <button type="submit" class="login-link">Back to Email Verification</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const boxes = Array.from(document.querySelectorAll('#otpBoxes .otp-box'));
        const hidden = document.getElementById('otpCode');
        const form = document.getElementById('otpForm');

        boxes.forEach((box, i) => {
            box.addEventListener('input', () => {
                box.value = box.value.replace(/\D/g, '').slice(0, 1);
                if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
            });
            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !box.value && i > 0) boxes[i - 1].focus();
            });
            box.addEventListener('paste', (e) => {
                e.preventDefault();
                const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
                text.split('').forEach((ch, j) => { if (boxes[j]) boxes[j].value = ch; });
                if (boxes[Math.min(text.length, 5)]) boxes[Math.min(text.length, 5)].focus();
            });
        });
        form.addEventListener('submit', () => { hidden.value = boxes.map(b => b.value).join(''); });
        if (boxes[0]) boxes[0].focus();

        let remaining = {{ (int) $expiresIn }};
        const el = document.getElementById('otpCountdown');
        const tick = () => {
            if (remaining < 0) remaining = 0;
            const m = String(Math.floor(remaining / 60)).padStart(2, '0');
            const s = String(remaining % 60).padStart(2, '0');
            el.textContent = m + ':' + s;
            if (remaining > 0) { remaining--; setTimeout(tick, 1000); }
        };
        tick();

        let cooldown = {{ (int) $cooldown }};
        const btn = document.getElementById('resendBtn');
        const cd = document.getElementById('resendCooldown');
        const cdTick = () => {
            if (cooldown > 0) {
                btn.disabled = true;
                cd.textContent = ' (wait ' + cooldown + 's)';
                cooldown--;
                setTimeout(cdTick, 1000);
            } else {
                btn.disabled = false;
                cd.textContent = '';
            }
        };
        cdTick();
    })();
    </script>
</body>
</html>
