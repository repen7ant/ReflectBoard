<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

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
