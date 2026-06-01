<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/github/redirect', [GitHubController::class, 'redirect'])->name('github.login');
Route::get('/auth/github/callback', [GitHubController::class, 'callback']);

Route::middleware('auth')->group(function () {
    Route::get('/board', fn () => view('board'))->name('board');
    Route::get('/done', fn () => view('done'))->name('done');
    Route::get('/analytics', fn () => view('analytics'))->name('analytics');
    Route::post('/telegram/link', [TelegramController::class, 'generateLinkToken'])->name('telegram.link');
    Route::delete('/account', function () {
        $user = auth()->user();
        try {
            Redis::del("auth_token:{$user->api_token}");
        } catch (Exception $e) {
            // Cache invalidation is best-effort
        }
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        $user->delete();

        return redirect('/');
    })->name('account.delete');
});

require __DIR__.'/auth.php';
