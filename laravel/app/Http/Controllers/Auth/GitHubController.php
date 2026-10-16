<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback()
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::firstOrCreate(
            ['email' => $githubUser->email],
            ['password' => bcrypt(Str::random(24))],
        );
        $user->update(['github_id' => $githubUser->id]);

        Auth::login($user);

        return redirect()->intended('/board');
    }
}
