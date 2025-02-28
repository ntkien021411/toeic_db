<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;


class AdminController extends Controller
{
    //GET /api/accounts?page=2
    // /api/accounts?username=student&page=1&email=student@gmail.com
    public function index(Request $request)
    {
        $query = Account::query() // Bỏ with('user') để không trả về user
        ->where('is_deleted', false)
        ->where(function ($q) {
            $q->whereDoesntHave('user') // Nếu không có user nào liên kết
              ->orWhereHas('user', function ($subQuery) {
                  $subQuery->where('role', '!=', 'ADMIN'); // Hoặc user không phải ADMIN
              });
        })
        ->select(['id', 'username', 'email', 'active_status', 'created_at']); // Chỉ lấy dữ liệu từ bảng account

        // Lọc theo username nếu có
        if ($request->has('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }

        // Lọc theo email nếu có
        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        // Chỉ chọn các trường cần thiết
        $accounts = $query->paginate($request->input('per_page', 10)); // Mặc định lấy 10 record mỗi trang

            return response()->json([
                'message' => 'Lấy danh sách tài khoản thành công',
                'code' => 200,
                'data' => $accounts->items(),
                'meta' => $accounts->total() > 0 ?[
                    'total' => $accounts->total(),
                    'current_page' => $accounts->currentPage(),
                    'per_page' => $accounts->perPage(),
                    'last_page' => $accounts->lastPage(),
                    'next_page_url' => $accounts->nextPageUrl(),
                    'prev_page_url' => $accounts->previousPageUrl(),
                    'first_page_url' => $accounts->url(1),
                    'last_page_url' => $accounts->url($accounts->lastPage())
                ] : null
            ],200);
    }

    // Data test
    // {
    //     "username": "testuser123",
    //     "password": "securePass123"
    // }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:account',
            'password' => 'required|min:6',
        ], [
            'username.required' => 'Tên đăng nhập không được để trống.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' =>  $validator->errors()
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Tạo tài khoản
            $account = Account::create([
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'active_status' => true,
                'active_date' => now()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Tạo tài khoản thành công!',
                'code' => 201,
                'data' => $account,
                'meta' => null
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Có lỗi xảy ra, vui lòng thử lại.',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'message_array' => $e->getMessage()
            ], 500);
        }
    }

    // {
    //     "email": "newemail@example.com",
    //     "active_status": false
    // }
    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        if (!$account) {
            return response()->json([
                'message' => 'Không tìm thấy tài khoản',
                'code' => 404,
                'data' => null,
                'meta' => null
            ], 404);
        }
    
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|unique:account,email,' . $id,
            'active_status' => 'nullable|boolean',
            'is_first' => 'nullable|boolean',
            'is_deleted' => 'nullable|boolean'
        ], [
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'active_status.boolean' => 'Trạng thái phải là true hoặc false.',
            'is_first.boolean' => 'Giá trị is_first phải là true hoặc false.',
            'is_deleted.boolean' => 'is_deleted phải là true hoặc false.'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' =>  $validator->errors()
            ], 400);
        }
    
        // Chỉ lấy các trường có trong request để cập nhật
        $data = $request->only(['email', 'active_status', 'is_first']);

        // Kiểm tra nếu is_deleted được truyền vào
        if ($request->has('is_deleted')) {
            $isDeleted = filter_var($request->input('is_deleted'), FILTER_VALIDATE_BOOLEAN);

            if ($isDeleted) {
                // Nếu is_deleted = true, đặt ngày xóa
                $data['deleted_at'] = now();
                $data['active_status'] = false;
            } else {
                // Nếu is_deleted = false, xóa deleted_at (khôi phục tài khoản)
                $data['deleted_at'] = false;
            }

            $data['is_deleted'] = $isDeleted;
        }

        // Cập nhật tài khoản
        $account->update($data);
    
        return response()->json([
            'message' => 'Cập nhật tài khoản thành công!',
            'code' => 200,
            'data' => $account,
            'meta' => null
        ], 200);
    }


}
