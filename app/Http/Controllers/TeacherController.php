<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class TeacherController extends Controller
{
        // c.1. Tìm kiếm và xem danh sách giáo viên (có phân trang)
    public function index(Request $request)
        {
            $query = User::query()
            ->where('role', 'TEACHER')
            ->where('is_deleted', false);

            if ($request->has('first_name')) {
                $query->where('first_name', 'like', "%{$request->first_name}%");
            }
            if ($request->has('last_name')) {
                $query->where('last_name', 'like', "%{$request->last_name}%");
            }
            if ($request->has('phone')) {
                $query->where('phone', 'like', "%{$request->phone}%");
            }
            if ($request->has('facebook_link')) {
                $query->where('facebook_link', 'like', "%{$request->facebook_link}%");
            }
            if ($request->has('birth_date')) {
                $query->where('birth_date', 'like', "%{$request->birth_date}%");
            }
            if ($request->has('gender')) {
                $query->where('gender', $request->gender);
            }
        
            $teachers = $query->paginate($request->input('per_page', 10));
        
            return response()->json([
                'message' => 'Lấy danh sách giáo viên thành công',
                'code' => 200,
                'data' => $teachers->items(),
                'meta' => $teachers->total() > 0 ? [
                    'total' => $teachers->total(),
                    'current_page' => $teachers->currentPage(),
                    'per_page' => $teachers->perPage(),
                    'last_page' => $teachers->lastPage(),
                    'next_page_url' => $teachers->nextPageUrl(),
                    'prev_page_url' => $teachers->previousPageUrl(),
                    'first_page_url' => $teachers->url(1),
                    'last_page_url' => $teachers->url($teachers->lastPage())
                ] : null
            ],200);
    }

       // c.2 Xem thông tin chi tiết của giáo viên
        public function show($id)
        {
            $teacher = User::where('role', 'TEACHER')->where('id', $id)->where('is_deleted', false)->first();
            if (!$teacher) {
                return response()->json([
                    'message' => 'Không tìm thấy giáo viên.',
                    'code' => 404,
                    'data' => null,
                    'meta' => null
                ], 404);
            }
            return response()->json([
                'message' => 'Thông tin giáo viên được lấy thành công.',
                'code' => 200,
                'data' => $teacher,
                'meta' => null
            ], 200);
        }

    // c.3. Thêm giáo viên
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:account,username',
            'password' => 'required|string|min:6',
            'email' => 'required|string|unique:account,email',
            'first_name' => 'nullable|string|max:50',
            'full_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:11|unique:user,phone',
            'image_link' => 'nullable|url',
            'facebook_link' => 'nullable|url',
            'address' => 'nullable|string|max:255'
        ], [
            'username.required' => 'Tên đăng nhập không được để trống.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'email.required' => 'Email không được để trống.',
            'email.unique' => 'Email đã tồn tại.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'birth_date.date' => 'Ngày sinh không hợp lệ.',
            'gender.in' => 'Giới tính chỉ được là MALE, FEMALE hoặc OTHER.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'phone.max' => 'Số điện thoại không được vượt quá 11 ký tự.',
            'image_link.url' => 'Liên kết ảnh không hợp lệ.',
            'facebook_link.url' => 'Liên kết Facebook không hợp lệ.',
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

        $account = Account::create([
            'username' => $request->username,
            'email' => $request->email,
            'active_date' => now(),
            'active_status' => true,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $teacher = User::create(array_merge(
            $request->only(['first_name', 'last_name','full_name', 'birth_date', 'gender', 'phone', 'image_link', 'facebook_link']),
            ['account_id' => $account->id, 'role' => 'TEACHER', 'is_deleted' => false]
        ));

        return response()->json([
            'message' => 'Giáo viên đã được thêm thành công.',
            'code' => 201,
            'data' => $teacher,
            'meta' => null
        ], 201);
    }


    // c.4. Chỉnh sửa thông tin giáo viên
    public function update(Request $request, $id)
    {
        // Kiểm tra giáo viên có tồn tại và có role là TEACHER không
            $teacher = User::where('id', $id)
            ->where('role', 'TEACHER')
            ->where('is_deleted', false)
            ->first();

        if (!$teacher) {
            return response()->json([
                'message' => 'Giáo viên không tồn tại hoặc đã bị xóa.',
                'code' => 404,
                'data' => $id,
                'meta' => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'full_name' => 'nullable|string|max:50',
            'gender' => 'nullable|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:11|unique:user,phone,' . $id,
            'image_link' => 'nullable|url',
            'facebook_link' => 'nullable|url',
            'address' => 'nullable|string|max:255',
            'is_deleted' => 'nullable|boolean'
        ], [
            'first_name.max' => 'Họ không được dài hơn 50 ký tự.',
            'last_name.max' => 'Tên không được dài hơn 50 ký tự.',
            'birth_date.date' => 'Ngày sinh không hợp lệ.',
            'gender.in' => 'Giới tính chỉ được là MALE, FEMALE hoặc OTHER.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'phone.max' => 'Số điện thoại không được vượt quá 11 ký tự.',
            'image_link.url' => 'Liên kết ảnh không hợp lệ.',
            'facebook_link.url' => 'Liên kết Facebook không hợp lệ.',
            'is_deleted.boolean' => 'Trạng thái xóa phải là true hoặc false.',
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

        // Nếu yêu cầu xóa giáo viên (soft delete)
        if ($request->has('is_deleted') && $request->is_deleted == true) {
            $teacher->update([
                'deleted_at' => now(),
                'is_deleted' => true
            ]);

            return response()->json([
                'message' => 'Giáo viên đã bị xóa mềm thành công.',
                'code' => 200,
                'data' => $teacher,
                'meta' => null
            ], 200);
        }
        
        // Cập nhật thông tin giáo viên
        $teacher->update($request->only([
            'first_name', 'last_name', 'birth_date', 'full_name', 'gender', 'phone', 'image_link', 'facebook_link'
        ]));

        return response()->json([
            'message' => 'Thông tin giáo viên đã được cập nhật.',
            'code' => 200,
            'data' => $teacher,
            'meta' => null
        ], 200);
    }


    

}
