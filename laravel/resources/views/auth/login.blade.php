<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in - ReflectBoard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/board.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .github-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .github-btn:hover {
            border-color: var(--text-muted);
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border);
        }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="modal" style="max-height: none;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <img src="/icon.svg" alt="ReflectBoard" style="width: 4rem; height: 4rem; margin: 0 auto 1rem;">
                <div class="modal-title" style="margin-bottom: 0;">Welcome back</div>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" style="color: #98bb6c; font-size: 0.875rem; margin-bottom: 1rem; text-align: center;" />

            <a href="{{ route('github.login') }}" class="btn github-btn">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                Log in with GitHub
            </a>

            <div class="divider">or continue with email</div>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                    <x-input-error :messages="$errors->get('email')" style="color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem;" />
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                    <x-input-error :messages="$errors->get('password')" style="color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem;" />
                </div>

                <div class="field" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                    <input id="remember_me" type="checkbox" name="remember" style="width: auto;">
                    <label for="remember_me" style="margin: 0; text-transform: none; letter-spacing: normal;">Remember me</label>
                </div>

                <div class="modal-actions" style="justify-content: space-between; align-items: center;">
                    <a href="{{ route('register') }}" style="color: var(--text-muted); font-size: 0.75rem; text-decoration: none;">Create account</a>
                    <button type="submit" class="btn btn-primary" style="width: 50%;">Log in</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
