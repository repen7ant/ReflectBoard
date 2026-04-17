<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReflectBoard &mdash; Clarity in motion</title>

    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="landing-wrapper">
        <div class="glow-bg"></div>

        <nav class="topbar" style="position: relative; border-bottom: none; background: transparent;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <img src="/icon.svg" alt="Logo" class="logo-icon">
                <span style="font-weight: 500; text-transform: uppercase; letter-spacing: 0.15em; font-size: 0.875rem; color: var(--text-muted);">
                    ReflectBoard
                </span>
            </div>

            <div style="display: flex; gap: 1rem; margin-left: auto;">
                @auth
                    <a href="{{ route('board') }}" class="btn btn-primary" style="text-decoration: none;">Board</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost" style="text-decoration: none;">Log in</a>
                @endauth
            </div>
        </nav>

        <main class="hero-section">
            <h1 class="hero-title">Clarity in motion.</h1>
            <p class="hero-subtitle">
                Reflect on your progress and achieve your daily goals.
            </p>

            <div class="hero-actions">
                @auth
                    <a href="{{ route('board') }}" class="btn btn-primary hero-btn">Open your board</a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary hero-btn">Sign up</a>
                    <a href="{{ route('github.login') }}" class="btn btn-ghost hero-btn">
                        <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                        Continue with GitHub
                    </a>
                @endauth
            </div>
        </main>
    </div>
</body>
</html>
