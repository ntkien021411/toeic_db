<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Token;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;

class CheckRoleAdmin
{

    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->bearerToken();

        if (!$tokenString) {
            return response()->json(['message' => 'Vui lòng cung cấp Token'], Response::HTTP_UNAUTHORIZED);
        }

        // Tìm token trong bảng tokens
        $token = Token::where('token', $tokenString)->first();
        if (!$token) {
             return response()->json(['message' => 'Token không hợp lệ'], 401);
         }
 
         // 3. Kiểm tra thời gian hết hạn của token
         if (!$token->expired_at || Carbon::parse($token->expired_at)->isPast()) {
             return response()->json(['message' => 'Token đã hết hạn'], 401);
         }

        // Tìm user theo account_id từ token
        $user = User::where('account_id', $token->account_id)->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], Response::HTTP_NOT_FOUND);
        }

        if (!$user || $user->role !== 'ADMIN') {
            return response()->json(['message' => 'Bạn không có quyền truy cập API này'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
