<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReflectBoard</title>

    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="landing-wrapper">
        <nav class="topbar landing-topbar">
            <div class="landing-logo">
                <img src="/icon.svg" alt="Logo" class="logo-icon">
                <span class="landing-logo-text">ReflectBoard</span>
            </div>
            <div class="landing-actions">
                @auth
                    <a href="{{ route('board') }}" class="btn btn-primary btn-link">Board</a>
                    <form method="POST" action="{{ route('logout') }}" class="landing-logout-form">
                        @csrf
                        <button type="submit" class="btn btn-ghost" onclick="return confirm('Log out?')">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-link">Log in</a>
                @endauth
            </div>
        </nav>
        <div class="hero-wrapper">
                <main class="hero-section">
                    <h1 class="hero-title">Clarity in motion.</h1>
                    <p class="hero-subtitle">
                        Reflect on your progress and achieve your daily goals.
                    </p>
                    <div class="hero-actions">
                        @auth
                            <a href="{{ route('board') }}" class="btn btn-primary hero-btn">Open your board</a>
                            <button onclick="generateTgToken()" class="btn btn-ghost hero-btn">Connect Telegram</button>
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
        </div>

        <section class="about-section">
            <p class="about-lead">
                Most task managers help you plan. ReflectBoard helps you understand
                <em>where your time actually goes</em> — including the parts you didn't plan for.
            </p>

            <div class="about-card">
                <div class="about-card-label">The problem</div>
                <div class="about-card-text">
                    You plan your day. But part of it disappears into things that weren't scheduled —
                    and that's exactly what's hardest to track. Two hours on YouTube.
                    A rabbit hole you didn't intend to go down.
                    <em>Time spent without awareness.</em>
                </div>
            </div>

            <div class="about-card">
                <div class="about-card-label">How it works</div>
                <div class="about-card-text">
                    Log both planned tasks and spontaneous ones. When you finish anything —
                    write a short reflection. Was it worth it? Did it matter?
                    Over time, <em>you start to see patterns. And change them.</em>
                </div>
            </div>

            <div class="about-card">
                <div class="about-card-label">What to log</div>
                <div class="about-card-text">
                    Not everything — only what <em>you</em> feel was unintentional or unaccounted for.
                    If you planned a movie night, skip it. If you ended up scrolling for an hour
                    and felt it — that's worth a note.
                </div>
            </div>

            <div class="about-card">
                <div class="about-card-label">The result</div>
                <div class="about-card-text">
                    A real picture of your day, not an ideal one.
                    Statistics that reflect <em>actual behaviour</em>, not just completed checkboxes.
                    Reflection that compounds into self-knowledge.
                </div>
            </div>
        </section>
    </div>
<script>
async function generateTgToken() {
    const res = await fetch('/telegram/link', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    });
    const { token } = await res.json();
    const command = `/link ${token}`;
    try {
        await navigator.clipboard.writeText(command);
        alert(`Copied to clipboard!\n\nSend this to the bot:\n${command}\n\nExpires in 5 minutes.`);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = command;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert(`Copied to clipboard!\n\nSend this to the bot:\n${command}\n\nExpires in 5 minutes.`);
    }
}
</script>
</body>
</html>
