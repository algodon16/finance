<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - Bestlink College of the Philippines</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #ffffff;
        }
        .login-page {
            min-height: 100vh;
            min-height: 100dvh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #ffffff;
        }
        .login-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 12px 32px rgba(15, 42, 90, 0.12);
            width: 100%;
            max-width: 400px;
            padding: 36px 32px 32px;
        }
        .login-logo-wrap { text-align: center; margin-bottom: 16px; }
        .login-logo { width: 84px; height: 84px; object-fit: contain; display: inline-block; }
        .login-title {
            margin: 0 0 4px;
            text-align: center;
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.4;
            color: #0f2a5a;
            letter-spacing: 0.3px;
        }
        .login-subtitle {
            margin: 0 0 24px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 500;
            color: #64748b;
        }
        .login-status {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            line-height: 1.6;
        }
        .login-alert {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            line-height: 1.6;
        }
        .login-group { margin-bottom: 18px; }
        .login-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            color: #0f2a5a;
        }
        .login-input {
            display: block;
            width: 100%;
            padding: 11px 14px;
            font-size: 0.9rem;
            line-height: 1.5;
            color: #0f2a5a;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .login-input::placeholder { color: #94a3b8; }
        .login-input:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.15);
        }
        .login-input.is-invalid { border-color: #dc2626; }
        .login-error { display: block; margin-top: 4px; font-size: 0.8125rem; color: #dc2626; }
        @media (max-width: 480px) {
            .login-card { padding: 28px 22px 26px; }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo-wrap">
                <img
                    src="{{ asset('images/bcp-logo.png') }}"
                    alt="Bestlink College logo"
                    class="login-logo"
                    width="84"
                    height="84"
                    onerror="this.style.display='none'"
                >
            </div>

            <h1 class="login-title">BESTLINK COLLEGE OF THE PHILIPPINES</h1>

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

            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="login-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="login-input @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        placeholder="Enter your email"
                        required
                        autofocus
                        autocomplete="username"
                    >
                    @error('email')
                        <span class="login-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="login-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="login-input @error('password') is-invalid @enderror"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    @error('password')
                        <span class="login-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="login-group" style="margin-bottom: 0;">
                    <x-ui.button type="submit" block>Login</x-ui.button>
                </div>
            </form>
        </div>
    </div>

    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
