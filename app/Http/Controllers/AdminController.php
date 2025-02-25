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
        $query = Account::query()
        ->whereNull('account.deleted_at') // Chỉ lấy account chưa bị xóa
        ->leftJoin('user', 'user.account_id', '=', 'account.id')
        ->where(function ($q) {
            $q->whereNull('user.id') // Nếu không có user nào liên kết
            ->orWhere('user.role', '!=', 'ADMIN'); // Hoặc user không phải ADMIN
        });

        // Lọc theo username nếu có
        if ($request->has('username')) {
            $query->where('account.username', 'like', '%' . $request->username . '%');
        }

        // Lọc theo email nếu có
        if ($request->has('email')) {
            $query->where('account.email', 'like', '%' . $request->email . '%');
        }

        // Chỉ chọn các trường cần thiết
        $accounts = $query->select([
                'account.id',
                'account.username',
                'account.email',
                'account.active_status',
                'account.created_at'
            ])
            ->paginate($request->input('per_page', 10)); // Mặc định lấy 10 record mỗi trang

        return response()->json([
            'data' => $accounts->items(),
            'total' => $accounts->total(),
            'current_page' => $accounts->currentPage(),
            'per_page' => $accounts->perPage(),
            'last_page' => $accounts->lastPage(),
            'next_page_url' => $accounts->nextPageUrl(),
            'prev_page_url' => $accounts->previousPageUrl(),
            'first_page_url' => $accounts->url(1),
            'last_page_url' => $accounts->url($accounts->lastPage())
        ]);
    }

    // Data test
    // {
    //     "username": "johndoe123",
    //     "password": "securepassword",
    //     "role": "TEACHER",
    //     "first_name": "John",
    //     "last_name": "Doe",
    //     "birth_date": "1990-05-15",
    //     "phone": "0987654321",
    //     "image_link": "https://example.com/profile.jpg",
    //     "facebook_link": "https://facebook.com/johndoe"
    // }
    
    

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:account',
            'password' => 'required|min:6',
            'role' => 'required|in:TEACHER,STUDENT',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'phone' => 'nullable|string|max:15|unique:User,phone',
            'image_link' => 'nullable|url',
            'facebook_link' => 'nullable|url',
        ], [
            'username.required' => 'Tên đăng nhập không được để trống.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'role.required' => 'Vai trò không được để trống.',
            'role.in' => 'Vai trò phải là TEACHER, STUDENT .',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'image_link.url' => 'Link ảnh không hợp lệ.',
            'facebook_link.url' => 'Link Facebook không hợp lệ.'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ.',
                'errors' => $validator->errors()
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
    
            // Tạo user liên kết với tài khoản
            $user = User::create([
                'account_id' => $account->id,
                'role' => $request->role,
                'first_name' => $request->first_name ?? null,
                'last_name' => $request->last_name ?? null,
                'birth_date' => $request->birth_date ?? null,
                'phone' => $request->phone ?? null,
                'image_link' => $request->image_link ?? null,
                'facebook_link' => $request->facebook_link ?? null
            ]);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Tạo tài khoản và người dùng thành công!',
                'account' => $account,
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Có lỗi xảy ra, vui lòng thử lại.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // {
    //     "email": "johndoe@example.com",
    //     "active_status": true,
    //     "is_first": false,
    //     "role": "ADMIN"
    // }
    
        public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|unique:account,email,' . $id,
            'active_status' => 'nullable|boolean',
            'is_first' => 'nullable|boolean',
            'role' => 'nullable|in:TEACHER,STUDENT',
            'is_deleted' => 'nullable|boolean'
        ], [
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'active_status.boolean' => 'Trạng thái phải là true hoặc false.',
            'is_first.boolean' => 'Giá trị is_first phải là true hoặc false.',
            'role.in' => 'Role không hợp lệ.',
            'is_deleted.boolean' => 'is_deleted phải là true hoặc false.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ.',
                'errors' => $validator->errors()
            ], 400);
        }

        // Chỉ lấy các trường cần cập nhật
        $data = $request->only(['email', 'active_status', 'is_first']);

        // Nếu is_deleted = false, đặt ngày xóa và vô hiệu hóa tài khoản
        if ($request->has('is_deleted') && $request->is_deleted == false) {
            $data['deleted_at'] = now();
            $data['active_status'] = false;
        }

        // Cập nhật tài khoản
        $account->update($data);

        // Nếu có role được truyền vào thì cập nhật role trong bảng User
        if ($request->has('role')) {
            $user = User::where('account_id', $id)->first();
            if ($user) {
                $user->update(['role' => $request->role]);
            } else {
                return response()->json([
                    'message' => 'Không tìm thấy user tương ứng để cập nhật role.'
                ], 404);
            }
        }

        // Cập nhật deleted_at của user nếu is_deleted = false
        if ($request->has('is_deleted') && $request->is_deleted == false) {
            $user = User::where('account_id', $id)->first();
            if ($user) {
                $user->update(['deleted_at' => now()]);
            }
        }

        return response()->json([
            'message' => 'Cập nhật tài khoản thành công!',
            'data' => $account
        ]);
    }


}
