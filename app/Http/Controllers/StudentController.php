<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class StudentController extends Controller
{
    public function listStudent(Request $request)
    {
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);

        // Truy vấn danh sách học viên, sử dụng Eager Loading để lấy email từ bảng Account
        $query = User::query()
            ->where('role', 'STUDENT')
            ->where('is_deleted', false)
            ->with('account:id,email'); // Chỉ lấy email từ bảng account để tối ưu hóa

        // Phân trang dữ liệu
        $students = $query->paginate($pageSize, ['id', 'first_name', 'last_name', 'birth_date', 'gender', 'phone', 'address', 'account_id'], 'page', $pageNumber);

        // Chuyển đổi dữ liệu theo format mong muốn
        $formattedStudents = $students->map(function ($student) {
            return [
                'id' => $student->id,
                'name' => "{$student->first_name} {$student->last_name}",
                'dob' => $student->birth_date,
                'gender' => $student->gender,
                'phone' => $student->phone,
                'email' => optional($student->account)->email, // Lấy email từ account (nếu có)
                'address' => $student->address
            ];
        });

        return response()->json([
            'message' => 'Lấy danh sách học viên thành công',
            'code' => 200,
            'data' => $formattedStudents,
            'meta' => $students->total() > 0 ? [
                'total' => $students->total(),
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'last_page' => $students->lastPage(),
                'next_page_url' => $students->nextPageUrl(),
                'prev_page_url' => $students->previousPageUrl(),
                'first_page_url' => $students->url(1),
                'last_page_url' => $students->url($students->lastPage())
            ] : null
        ], 200);
    }
    


        // c.1. Tìm kiếm và xem danh sách học viên (có phân trang)
    public function index(Request $request)
        {
           $query = User::query()
            ->where('role', 'STUDENT')
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

            $students = $query->paginate($request->input('per_page', 10));

            return response()->json([
                'message' => 'Lấy danh sách học viên thành công',
                'code' => 200,
                'data' => $students->items(),
                'meta' => $students->total() > 0 ? [
                    'total' => $students->total(),
                    'current_page' => $students->currentPage(),
                    'per_page' => $students->perPage(),
                    'last_page' => $students->lastPage(),
                    'next_page_url' => $students->nextPageUrl(),
                    'prev_page_url' => $students->previousPageUrl(),
                    'first_page_url' => $students->url(1),
                    'last_page_url' => $students->url($students->lastPage())
                ] : null
            ], 200);
    }
    

       // c.2 Xem thông tin chi tiết của học viên
        public function show($id)
        {
            $students = User::where('role', 'STUDENT')->where('id', $id)->where('is_deleted', false)->first();
            if (!$students) {
                return response()->json([
                    'message' => 'Không tìm thấy học viên.',
                    'code' => 404,
                    'data' => null,
                    'meta' => null
                ], 404);
            }
            return response()->json([
                'message' => 'Thông tin  học viên được lấy thành công.',
                'code' => 200,
                'data' => $students,
                'meta' => null
            ], 200);
        }

    // c.3. Thêm học viên
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:account,username',
            'email' => 'required|string|unique:account,email',
            'password' => 'required|string|min:6',
            'first_name' => 'nullable|string|max:50',
            'full_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:11|unique:user,phone',
            'image_link' => 'nullable|url',
            'facebook_link' => 'nullable|url',
            'address' => 'nullable|string|max:255',
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

        $student = User::create(array_merge(
            $request->only(['first_name', 'last_name','full_name',  'birth_date', 'gender', 'phone', 'image_link', 'facebook_link']),
            ['account_id' => $account->id, 'role' => 'STUDENT', 'is_deleted' => false]
        ));

        return response()->json([
            'message' => 'Học viên đã được thêm thành công.',
            'code' => 201,
            'data' => $student,
            'meta' => null
        ], 201);
    }


    // c.4. Chỉnh sửa thông tin học viên
    public function update(Request $request, $id)
    {
        // Kiểm tra giáo viên có tồn tại và có role là TEACHER không
        $teacher = User::where('id', $id)
        ->where('role', 'STUDENT')
        ->where('is_deleted', false)
        ->first();

        if (!$teacher) {
            return response()->json([
                'message' => 'Học viên không tồn tại hoặc đã bị xóa.',
                'code' => 404,
                'data' => $id,
                'meta' => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'full_name' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
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
                'message' => 'Học viên đã bị xóa mềm thành công.',
                'code' => 200,
                'data' => $teacher,
                'meta' => null
            ], 200);
        }
        
        // Cập nhật thông tin giáo viên
        $teacher->update($request->only([
            'first_name', 'last_name','full_name', 'birth_date', 'gender', 'phone', 'image_link', 'facebook_link'
        ]));

        return response()->json([
            'message' => 'Thông tin học viên đã được cập nhật.',
            'code' => 200,
            'data' => $teacher,
            'meta' => null
        ], 200);
    }


    

}
