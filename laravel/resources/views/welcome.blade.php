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
                @auth
                    <a href="https://t.me/{{ config('services.telegram.bot_username') }}" target="_blank" title="Open Telegram bot" style="display:flex;align-items:center;padding:0.625rem 0.25rem;color:var(--text-muted);opacity:0.7;transition:opacity 0.15s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">
                        <svg viewBox="0 0 24 24" width="30" height="30" fill="none"><circle cx="12" cy="12" r="12" fill="#2AABEE"/><path fill="white" d="M5.89 11.72l11.57-4.46c.54-.2 1.01.13.96.66l-1.97 9.28c-.14.66-.54.82-1.08.51l-3-2.21-1.45 1.39c-.16.16-.3.3-.6.3l.21-3.05 5.56-5.02c.24-.21-.05-.33-.37-.12L7.19 13.9l-2.96-.92c-.64-.2-.66-.64.13-.95z"/></svg>
                    </a>
                @endauth
            </div>
            <div class="landing-actions">
                @auth
                    <a href="{{ route('board') }}" class="btn btn-primary btn-link">Board</a>
                    <div class="nav-user" x-data="{ open: false, confirming: false }" @keydown.escape.window="open = false; confirming = false">
                        <button class="nav-user-btn nav-link" @click="open = !open">
                            <span class="nav-user-chevron" :class="{ 'nav-user-chevron--open': open }">▾</span>
                        </button>
                        <div class="nav-dropdown" x-show="open" x-cloak @click.outside="open = false; confirming = false">
                            <div class="nav-dropdown-name">{{ auth()->user()->name ?? auth()->user()->email }}</div>
                            <div class="nav-dropdown-divider"></div>
                            <template x-if="confirming !== 'logout'">
                                <button class="nav-dropdown-item" @click="confirming = 'logout'">Log out</button>
                            </template>
                            <template x-if="confirming === 'logout'">
                                <div class="nav-dropdown-confirm">
                                    <span class="nav-dropdown-confirm-text">Log out?</span>
                                    <div class="nav-dropdown-confirm-actions">
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-xs">Yes</button>
                                        </form>
                                        <button class="btn btn-ghost btn-xs" @click="confirming = false">Cancel</button>
                                    </div>
                                </div>
                            </template>
                            <div class="nav-dropdown-divider"></div>
                            <template x-if="confirming !== 'delete'">
                                <button class="nav-dropdown-item nav-dropdown-item--danger" @click="confirming = 'delete'">Delete account</button>
                            </template>
                            <template x-if="confirming === 'delete'">
                                <div class="nav-dropdown-confirm">
                                    <span class="nav-dropdown-confirm-text">Delete everything?</span>
                                    <div class="nav-dropdown-confirm-actions">
                                        <form method="POST" action="{{ route('account.delete') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">Yes, delete</button>
                                        </form>
                                        <button class="btn btn-ghost btn-xs" @click="confirming = false">Cancel</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
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
                            @if(auth()->user()->telegram_id)
                                <a href="https://t.me/{{ config('services.telegram.bot_username') }}" target="_blank" class="btn btn-ghost hero-btn">Open Telegram bot</a>
                            @else
                                <button onclick="generateTgToken()" class="btn btn-ghost hero-btn">Connect Telegram</button>
                            @endif
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
    const { url } = await res.json();
    window.open(url, '_blank');
}
</script>
</body>
</html>
