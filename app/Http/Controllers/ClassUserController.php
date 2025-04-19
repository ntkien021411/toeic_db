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

        // **Bắt đầu kiểm tra lịch học trùng**
        // Lấy thông tin ngày học và thời gian của lớp mới
        $newClassDays = json_decode($class->days, true); // Chuyển đổi chuỗi JSON thành mảng
        $newStartDate = $class->start_date;
        $newEndDate = $class->end_date;
        $newStartTime = $class->start_time; // Thời gian bắt đầu của lớp mới
        $newEndTime = $class->end_time; // Thời gian kết thúc của lớp mới

        // Lấy tất cả các lớp mà học sinh đã tham gia
        $studentClasses = ClassUser::where('user_id', $request->user_id)
            ->with('class') // Eager load để lấy thông tin lớp
            ->get();

        // Kiểm tra từng lớp mà học sinh đã tham gia
        foreach ($studentClasses as $studentClass) {
            $studentClassDays = json_decode($studentClass->class->days, true); // Chuyển đổi chuỗi JSON thành mảng
            $studentStartDate = $studentClass->class->start_date;
            $studentEndDate = $studentClass->class->end_date;
            $studentStartTime = $studentClass->class->start_time; // Thời gian bắt đầu của lớp đã có
            $studentEndTime = $studentClass->class->end_time; // Thời gian kết thúc của lớp đã có

            // **Kiểm tra xem có trùng lịch không**
            if (
                (strtotime($newStartDate) <= strtotime($studentEndDate) && strtotime($newEndDate) >= strtotime($studentStartDate)) && // Kiểm tra khoảng thời gian
                array_intersect($newClassDays, $studentClassDays) && // Kiểm tra ngày học có trùng không
                (strtotime($newStartTime) < strtotime($studentEndTime) && strtotime($newEndTime) > strtotime($studentStartTime)) // Kiểm tra thời gian có trùng không
            ) {
                return response()->json([
                    'message' => 'Lịch học bị trùng với lớp đã có.',
                    'code' => 409,
                    'data' => null,
                    'meta' => null
                ], 409);
            }
        }
        // **Kết thúc kiểm tra lịch học trùng**

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
            ->where('is_deleted', false)
            ->with(['class' => function($query) {
                $query->where('is_deleted', false);
            }])
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

    public function getClassDetail($class_id)
    {
        // Lấy thông tin lớp học và giáo viên
        $class = Classes::where('id', $class_id)
            ->where('is_deleted', false)
            ->with(['teacher' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'full_name', 'image_link', 'phone', 'account_id')
                      ->with(['account' => function($q) {
                          $q->select('id', 'email');
                      }]);
            }])
            ->first();

        if (!$class) {
            return response()->json([
                'message' => 'Không tìm thấy lớp học hoặc lớp học đã bị xóa.',
                'code' => 404,
                'data' => null,
                'meta' => null
            ], 404);
        }

        // Lấy thông tin phân trang từ request
        $pageNumber = request('pageNumber', 1);
        $pageSize = request('pageSize', 10);

        // Lấy danh sách học viên của lớp có phân trang
        $students = ClassUser::where('class_id', $class_id)
            ->where('is_deleted', false)
            ->with(['user' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'full_name', 'birth_date', 'gender', 'phone', 'address', 'account_id')
                      ->where('role', 'STUDENT')
                      ->where('is_deleted', false)
                      ->with(['account' => function($q) {
                          $q->select('id', 'email')
                            ->where('is_deleted', false);
                      }]);
            }])
            ->paginate($pageSize, ['*'], 'page', $pageNumber);

        // Map lại để chỉ lấy thông tin user và thêm email từ account
        $students->setCollection($students->getCollection()->map(function ($classUser) {
            $user = $classUser->user;
            if ($user) {
                $userData = $user->toArray();
                $userData['email'] = $user->account ? $user->account->email : null;
                unset($userData['account']); // Xóa thông tin account không cần thiết
                unset($userData['account_id']); // Xóa account_id không cần thiết
                return $userData;
            }
            return null;
        }));

        // Lọc bỏ các giá trị null (trường hợp user đã bị xóa)
        $students->setCollection(
            $students->getCollection()->filter()
        );

        // Format lại thông tin giáo viên
        $teacherInfo = null;
        if ($class->teacher) {
            $teacherInfo = [
                'id' => $class->teacher->id,
                'first_name' => $class->teacher->first_name,
                'last_name' => $class->teacher->last_name,
                'full_name' => $class->teacher->full_name,
                'image_link' => $class->teacher->image_link,
                'phone' => $class->teacher->phone,
                'email' => $class->teacher->account ? $class->teacher->account->email : null
            ];
        }

        return response()->json([
            'message' => 'Thông tin chi tiết lớp học.',
            'code' => 200,
            'data' => [
                'class_info' => [
                    'id' => $class->id,
                    'class_code' => $class->class_code,
                    'class_name' => $class->class_name,
                    'class_type' => $class->class_type,
                    'start_date' => $class->start_date,
                    'start_time' => date('H:i', strtotime($class->start_time)),
                    'end_time' => date('H:i', strtotime($class->end_time)),
                    'days' => $class->days,
                    'student_count' => $class->student_count,
                    'is_full' => $class->is_full,
                    'status' => $class->status,
                    'teacher' => $teacherInfo
                ],
                'students' => array_values($students->items()) // Convert to array
            ],
            'meta' => $students->total() > 0 ? [
                'total' => $students->total(),
                'pageCurrent' => $students->currentPage(),
                'pageSize' => $students->perPage(),
                'totalPage' => $students->lastPage()
            ] : null
        ], 200);
    }
}
