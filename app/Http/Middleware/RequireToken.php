<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Token;
use Carbon\Carbon;

class RequireToken
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Lấy token từ header Authorization
        $tokenString = $request->bearerToken();

        if (!$tokenString) {
            return response()->json(['message' => 'Vui lòng cung cấp Token'], 401);
        }

        // 2. Kiểm tra token trong database
        $token = Token::where('token', $tokenString)->first();

        if (!$token || Carbon::parse($token->expired_at)->isPast()) {
            return response()->json(['message' => 'Token không hợp lệ hoặc đã hết hạn'], 401);
        }

        return $next($request);
    }
}
