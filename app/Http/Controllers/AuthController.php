<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;
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

        // 4. Kiểm tra active_status
        if (!$account->active_status) {
            return response()->json([
                'message' => 'Tài khoản chưa được kích hoạt',
                'code' => 403,
                'data' => null,
                'meta' => null
            ], 403);
        }

        // 5. Tạo Access Token & Refresh Token
        $accessToken = Str::random(60); // Token ngẫu nhiên
        $refreshToken = Str::random(60); // Token làm mới
        $expiredAt = Carbon::now()->addHours(24); // Token hết hạn sau 2 phút
        $refreshExpiredAt = Carbon::now()->addDays(3); // Refresh token hết hạn sau 3 ngày
 
        // 6. Lưu token vào database
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
        
        // 7. Trả về response chứa token
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

    public function checkAccount(Request $request)
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
        $account = Account::where('id', $token->account_id)->first();

        if (!$account) {
            return response()->json(['message' => 'Không tìm thấy tài khoản người dùng'], Response::HTTP_NOT_FOUND);
        }
        // Tìm user theo account_id từ token
        $user = User::where('account_id', $token->account_id)->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], Response::HTTP_NOT_FOUND);
        }

            return response()->json([
                'message' => 'Lấy thông tin người dùng thành công ',
                'code' => 200,
                'data' => (object) [
                    'id' => $user->id,
                    'fullName' => $user->full_name ?? '',
                    'username' => $account->username ?? '',
                    'email' => $account->email ?? '',
                    'role' => $user->role,
                    'isAdmin' => $user->role === 'ADMIN',
                    'isClient' => $user->role !== 'ADMIN'
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

    //  "http://localhost:8000/api/forgot-password?email=user@example.com"
    public function forgotPassword(Request $request)
    {
            // Lấy email từ query string
            $email = trim($request->query('email'));

            // Kiểm tra nếu không có email
            if (!$email) {
                return response()->json([
                    'message' => 'Vui lòng cung cấp email',
                    'code' => 400,
                    'data' => null,
                ], 400);
            }

            // Tìm user theo email
            $account = Account::where('email', $email)->first();

            if (!$account) {
                return response()->json([
                    'message' => 'Email không tồn tại',
                    'code' => 404,
                    'data' => null,
                ], 404);
            }
            $user = User::where('account_id', $account->id)->first();
            if ($user->role === 'ADMIN') {
                return response()->json([
                    'message' => 'Chức năng không khả dụng cho tài khoản ADMIN',
                    'code' => 404,
                    'data' => null,
                ], 404);
            }

              // 4. Tạo Access Token & Refresh Token
                $accessToken = Str::random(60); // Token ngẫu nhiên
                $refreshToken = Str::random(60); // Token làm mới
                $expiredAt = Carbon::now()->addMinutes(30); // Token hết hạn sau 2 phút
        
                // 5. Lưu token vào database
                Token::updateOrCreate(
                    ['account_id' => $account->id],
                    [
                        'token' => $accessToken,
                        'refresh_token' => $refreshToken,
                        'expired_at' => $expiredAt
                    ]
                );

            // Gửi email chứa mật khẩu mới (Dùng queue để gửi không bị chậm)
            try {
                $resetLink = url("http://localhost:5173/auth/reset-password?username={$account->username}&email={$request->email}&token={$accessToken}");
                Mail::to($account->email)->send(new ResetPasswordMail($resetLink));

                return response()->json([
                    'message' => 'Chúng tôi đã gửi email khôi phục mật khẩu!',
                    'notice' => 'Trong trường hợp không nhận được mail trong 2p xin vui lòng kiểm tra lại email đã nhập!',
                    'code' => 200,
                    'data' => [
                        'email' => $email
                    ],
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Không thể gửi email. Vui lòng thử lại sau',
                    'code' => 500,
                    'data' => null,
                ], 500);
            }
    } 
    public function resetPassword(Request $request)
    {
          // Kiểm tra dữ liệu request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:Account,email',
            'token' => 'required|string',
            'new_password' => 'required|min:5'
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.exists' => 'Email này không tồn tại trong hệ thống.',
            'token.required' => 'Token là bắt buộc.',
            'new_password.required' => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min' => 'Mật khẩu phải có ít nhất 5 ký tự.',
        ]);

        // Nếu validate thất bại, trả về lỗi
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu nhập vào không hợp lệ.',
                'errors' => $validator->errors()
            ], 400);
        }

        // Kiểm tra token có hợp lệ không
        $token = Token::where('token', $request->token)->first();
        if (!$token) {
            return response()->json([
                'message' => 'Token không hợp lệ hoặc đã hết hạn.',
                'code' => 400,
                'data' => null
            ], 400);
        }


        // Lấy tài khoản theo email
        $account = Account::where('email', $request->email)->first();
        if (!$account) {
            return response()->json([
                'message' => 'Không tìm thấy tài khoản.',
                'code' => 404,
                'data' => null
            ], 404);
        }

        // Cập nhật mật khẩu mới
        $account->update(['password' => Hash::make($request->new_password)]);

        // Xóa token sau khi sử dụng
        $token->delete();

        return response()->json([
            'message' => 'Mật khẩu đã được đặt lại thành công!',
            'code' => 200,
            'data' => [
                'email' => $account->email
            ]
        ], 200);
    }

   
}
