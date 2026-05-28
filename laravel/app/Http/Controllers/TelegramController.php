<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    public function generateLinkToken(Request $request): JsonResponse
    {
        $token = Str::random(32);
        Redis::connection('bot')->setex("tg_link:{$token}", 300, $request->user()->id);

        return response()->json(['token' => $token]);
    }
}
