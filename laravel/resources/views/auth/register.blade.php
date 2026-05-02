<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ReflectBoard</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-container">
        <div class="modal" style="max-height: none;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <img src="/icon.svg" alt="ReflectBoard" style="width: 4rem; height: 4rem; margin: 0 auto 1rem;">
                <div class="modal-title" style="margin-bottom: 0;">Create an account</div>
            </div>

            <a href="{{ route('github.login') }}" class="btn github-btn">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
                Continue with GitHub
            </a>

            <div class="divider">or register with email</div>

            <form method="POST" action="{{ route('register') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required>
                    <x-input-error :messages="$errors->get('email')" style="color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem;" />
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required>
                    <x-input-error :messages="$errors->get('password')" style="color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem;" />
                </div>

                <div class="field" style="margin-bottom: 1.5rem;">
                    <label for="password_confirmation">Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required>
                    <x-input-error :messages="$errors->get('password_confirmation')" style="color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem;" />
                </div>

                <div class="modal-actions" style="justify-content: space-between; align-items: center;">
                    <a href="{{ route('login') }}" style="color: var(--text-muted); font-size: 0.75rem; text-decoration: none;">Already registered?</a>
                    <button type="submit" class="btn btn-primary" style="width: 50%;">Register</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
