<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Token;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;


class CheckRoleUser
{
    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->bearerToken();

        if (!$tokenString) {
            return response()->json(['message' => 'Vui lòng cung cấp Token'], Response::HTTP_UNAUTHORIZED);
        }

        // Tìm token trong bảng tokens
        $token = Token::where('token', $tokenString)->first();

        if (!$token || now()->greaterThan($token->expired_at)) {
            return response()->json(['message' => 'Token không hợp lệ hoặc đã hết hạn'], Response::HTTP_UNAUTHORIZED);
        }

        // Tìm user theo account_id từ token
        $user = User::where('account_id', $token->account_id)->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], Response::HTTP_NOT_FOUND);
        }

        // Kiểm tra role có thuộc TEACHER hoặc STUDENT không
        if (!in_array($user->role, ['TEACHER', 'ADMIN'])) {
            return response()->json(['message' => 'Bạn không có quyền truy cập API này'], Response::HTTP_FORBIDDEN);
        }
        // Gán thông tin người dùng vào request để sử dụng trong controller
        $request->attributes->set('authenticatedUser', $user);


        return $next($request);
    }
}
