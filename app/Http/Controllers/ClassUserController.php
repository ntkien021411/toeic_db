<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Classes;
use App\Models\ClassUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class ClassUserController extends Controller
{
    public function addStudentToClass(Request $request)
    {
        // Kiểm tra đầu vào
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'class_id' => 'required|integer'
        ], [
            'user_id.required' => 'Student ID là bắt buộc.',
            'user_id.integer' => 'Student ID phải là số.',
            'class_id.required' => 'Class ID là bắt buộc.',
            'class_id.integer' => 'Class ID phải là số.',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu đầu vào không hợp lệ.',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' =>  $validator->errors()
            ], 400);
        }

        // Lấy thông tin lớp học
        $class = Classes::find($request->class_id);
        
        // Lấy thông tin user
        $user = User::find($request->user_id);

        // Kiểm tra user có phải role STUDENT không
        if ($user->role !== 'STUDENT') {
            return response()->json([
                'message' => 'User không phải là STUDENT.',
                'code' => 403,
                'data' => null,
                'meta' => null
            ], 403);
        }

        // Kiểm tra user đã có trong class chưa
        if (ClassUser::where('class_id', $request->class_id)->where('user_id', $request->user_id)->exists()) {
            return response()->json([
                'message' => 'Student đã có trong class.',
                'code' => 409,
                'data' => null,
                'meta' => null
            ], 409);
        }

        // Thêm user vào class
        $classUser = ClassUser::create([
            'class_id' => $request->class_id,
            'user_id' => $request->user_id
        ]);

        // Cập nhật student_count của class
        $class->increment('student_count');

        return response()->json([
            'message' => 'Student đã được thêm vào class.',
            'code' => 201,
            'data' => $classUser,
            'meta' => null
        ], 201);

    }

    public function getStudentClasses(Request $request,$user_id)
    {

        // Lấy thông tin user
        $user = User::find($user_id);

        // Kiểm tra user có phải role STUDENT không
        if ($user->role !== 'STUDENT') {
            return response()->json([
                'message' => 'User không phải là STUDENT.',
                'code' => 403,
                'data' => null,
                'meta' => null
            ], 403);
        }

        // Lấy danh sách lớp học của học viên có phân trang
            $classes = ClassUser::where('user_id', $user_id)
            ->whereNull('deleted_at') // Bỏ qua bản ghi bị xóa mềm
            ->with('class') // Eager load thông tin class
            ->paginate($request->input('per_page', 2));

            // Map lại để chỉ lấy thông tin class
        $classes->getCollection()->transform(fn($classUser) => $classUser->class);

        return response()->json([
            'message' => 'Danh sách lớp học của học viên.',
            'code' => 200,
            'data' => $classes->items(), // Chỉ lấy danh sách lớp học
            'meta' => $classes->total() > 0 ? [
                'total' => $classes->total(),
                'current_page' => $classes->currentPage(),
                'per_page' => $classes->perPage(),
                'last_page' => $classes->lastPage(),
                'next_page_url' => $classes->nextPageUrl(),
                'prev_page_url' => $classes->previousPageUrl(),
                'first_page_url' => $classes->url(1),
                'last_page_url' => $classes->url($classes->lastPage())
            ] : null
        ], 200);
    }
}
