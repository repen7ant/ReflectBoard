@props(['active' => ''])

<nav class="topbar">
    <div class="topbar-logo">
        <a href="/">
            <img src="/icon.svg" alt="ReflectBoard" class="logo-icon">
        </a>
    </div>
    <div class="nav-links">
        <a href="/board" class="nav-link {{ $active === 'board' ? 'active' : '' }}">Board</a>
        <a href="/done" class="nav-link {{ $active === 'done' ? 'active' : '' }}">Done</a>
        <a href="/analytics" class="nav-link {{ $active === 'analytics' ? 'active' : '' }}">Analytics</a>
    </div>
    <div class="nav-user" x-data="{ open: false, confirming: false }" @keydown.escape.window="open = false; confirming = false">
        <button class="nav-user-btn nav-link" @click="open = !open" :aria-expanded="open">
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
</nav>
