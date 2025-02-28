<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Account;
use App\Models\Token;
use Carbon\Carbon;
use App\Models\User;
class AuthController extends Controller
{
     // ✅ API Login: Đăng nhập bằng email hoặc username và trả về token
     public function login(Request $request)
     {
         // 1. Kiểm tra đầu vào (email/username & password không được rỗng)
         $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
         ] ,[
            'username.required'  => 'Username là bắt buộc.',
            'password.required'    => 'Password là bắt buộc.'
        ]);
 
         if ($validator->fails()) {
            return response()->json([
                'message' => 'Login: Dữ liệu không hợp lệ',
                'code' => 400,
                'data' => null,
                'meta' => null
            ], 400);
        }
 
         // 2. Tìm tài khoản theo email hoặc username
         $account = Account::where('username', $request->username)
             ->first();
        
         // 3. Kiểm tra tài khoản có tồn tại và mật khẩu có đúng không
         if (!$account || !Hash::check($request->password, $account->password)) {
            return response()->json([
                'message' => 'Tài khoản hoặc mật khẩu không chính xác',
                'code' => 404,
                'data' => null,
                'meta' => null
            ], 404);        
        }
 
         // 4. Tạo Access Token & Refresh Token
         $accessToken = Str::random(60); // Token ngẫu nhiên
         $refreshToken = Str::random(60); // Token làm mới
         $expiredAt = Carbon::now()->addMinutes(30); // Token hết hạn sau 2 phút
         $refreshExpiredAt = Carbon::now()->addDays(3); // Refresh token hết hạn sau 3 ngày
 
         // 5. Lưu token vào database
         Token::updateOrCreate(
             ['account_id' => $account->id],
             [
                 'token' => $accessToken,
                 'refresh_token' => $refreshToken,
                 'expired_at' => $expiredAt
             ]
         );
         $userData = User::where('account_id', $account->id)->first();
         // Kiểm tra nếu $userData là null
         $account->setAttribute('fullname', $userData->full_name ?? '');
         
         // 6. Trả về response chứa token
         return response()->json([
            'message' => 'Đăng nhập thành công',
            'code' => 200,
            'data' => [
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $expiredAt,
                'account' => $account,
                'role' => $userData->role ?? ''
            ],
            'meta' => null
        ], 200);
     }
 

     public function refresh(Request $request)
     {
         // 1. Kiểm tra đầu vào
         $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'refresh_token' => 'required',
        ],[
            'user_id.required'  => 'User ID là bắt buộc.',
            'user_id.integer'   => 'User ID phải là số nguyên.',
            'refresh_token.required' => 'Refresh token là bắt buộc.'
        ]);
     
         if ($validator->fails()) {
            return response()->json([
                'message' => 'Refresh: Dữ liệu không hợp lệ',
                'code' => 400,
                'data' => null,
                'meta' => null
            ], 400);
         }
     
         // 2. Kiểm tra token hợp lệ với user_id
         $token = Token::where('refresh_token', $request->refresh_token)
                     ->where('account_id', $request->user_id)
                     ->first();
     
        if (!$token) {
            return response()->json([
                'message' => 'Refresh Token không hợp lệ',
                'code' => 401,
                'data' => null,
                'meta' => null
            ], 401);
        }

        if (now()->greaterThan($token->expired_at)) {
            return response()->json([
                'message' => 'Refresh Token đã hết hạn',
                'code' => 401,
                'data' => null,
                'meta' => null
            ], 401);
        }
     
         // 3. Tạo Token mới
         $newToken = Str::random(60);
         $expiredAt = Carbon::now()->addMinutes(60); // Token mới hết hạn sau 60 phút
     
         // 4. Cập nhật token mới vào database
         $token->update([
             'token' => $newToken,
             'expired_at' => $expiredAt
         ]);
     
         // 5. Trả về token mới
         return response()->json([
            'message' => 'Làm mới token thành công',
            'code' => 200,
            'data' => [
                'token' => $newToken,
                'expires_in' => $expiredAt
            ],
            'meta' => null
        ], 200);
     }

     public function logout(Request $request)
     {
         // 1. Lấy token từ Header Authorization
        $tokenString = $request->bearerToken();

        if (!$tokenString) {
            return response()->json([
                'message' => 'Vui lòng cung cấp Token',
                'code' => 401,
                'data' => null,
                'meta' => null
            ], 401);
        }

        // 2. Kiểm tra dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Logout: Dữ liệu không hợp lệ',
                'code' => 400,
                'data' => null,
                'meta' => null
            ], 400);
        }

        // 3. Tìm token trong database
        $token = Token::where('token', $tokenString)->first();

        if (!$token) {
            return response()->json([
                'message' => 'Token không hợp lệ',
                'code' => 401,
                'data' => null,
                'meta' => null
            ], 401);
        }

        // 4. Kiểm tra token có thuộc về user không
        if ($token->account_id != $request->user_id) {
            return response()->json([
                'message' => 'Token không thuộc về user',
                'code' => 403,
                'data' => null,
                'meta' => null
            ], 403);
        }

        // 5. Xóa token khỏi database
        $token->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công',
            'code' => 200,
            'data' => null,
            'meta' => null
        ], 200);
     }
}
