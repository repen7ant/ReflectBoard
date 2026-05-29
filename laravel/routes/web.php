<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health/redis', function () {
    Redis::set('laravel_ping', 'pong', 'EX', 10);
    $val = Redis::get('laravel_ping');

    return response()->json(['status' => 'ok', 'value' => $val]);
});

Route::get('/health/db', function () {
    $result = DB::select('SELECT 1 as result');

    return response()->json(['status' => 'ok', 'result' => $result]);
});

Route::get('/auth/github/redirect', [GitHubController::class, 'redirect'])->name('github.login');
Route::get('/auth/github/callback', [GitHubController::class, 'callback']);

Route::middleware('auth')->group(function () {
    Route::get('/board', fn () => view('board'))->name('board');
    Route::get('/done', fn () => view('done'))->name('done');
    Route::get('/analytics', fn () => view('analytics'))->name('analytics');
    Route::post('/telegram/link', [TelegramController::class, 'generateLinkToken'])->name('telegram.link');
});

require __DIR__.'/auth.php';
