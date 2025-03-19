<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Classes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class ClassController extends Controller
{
    public function store(Request $request)
    {
       // Kiểm tra xem teacher_id có phải là giáo viên không
        $teacher = User::where('id', $request->teacher)
        ->where('is_deleted', false)
        ->first(); // Lấy kết quả đầu tiên từ truy vấn

        if (!$teacher) {
            return response()->json([
            'message' => 'Người dùng không tồn tại.',
            'code' => 404,
            'data' => null,
            'meta' => null
            ], 404);
        }

        // Kiểm tra vai trò của giáo viên
        if ($teacher->role !== 'TEACHER') {
            return response()->json([
            'message' => 'Người dùng không phải là giáo viên.',
            'code' => 400,
            'data' => null,
            'meta' => null
            ], 400);
        }
    $validator = Validator::make($request->all(), [
        'class_code'          => 'required|string:Room,class_code',
        'class_type'          => 'required|string|in:Beginner,Toeic A,Toeic B',
        'start_date'          => 'required|date',
        'end_date'            => 'required|date|after_or_equal:start_date',
        'start_time'          => 'required|date_format:H:i',
        'end_time'            => 'required|date_format:H:i|after:start_time',
        'days'                => 'required|array|min:1',
        'days.*'              => ['required', 'regex:/^\d{2}-\d{2}-\d{4}$/'], // dd-mm-yyyy
        'number_of_students'  => 'required|integer|min:1',
        'teacher'             => 'required|integer|exists:User,id'
    ], [
        'class_code.required'      => 'Mã lớp học là bắt buộc.',
        'class_code.unique'        => 'Mã lớp học đã tồn tại.',
        'class_type.required'      => 'Loại lớp học là bắt buộc.',
        'class_type.in'            => 'Loại lớp học không hợp lệ. Chỉ chấp nhận: Tập sự, Toeic A, Toeic B.',
        'start_date.required'      => 'Ngày bắt đầu là bắt buộc.',
        'start_date.date'          => 'Ngày bắt đầu không hợp lệ.',
        'end_date.required'        => 'Ngày kết thúc là bắt buộc.',
        'end_date.date'            => 'Ngày kết thúc không hợp lệ.',
        'end_date.after_or_equal'  => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
        'start_time.required'      => 'Giờ bắt đầu là bắt buộc.',
        'start_time.date_format'   => 'Giờ bắt đầu không hợp lệ. Định dạng đúng là HH:mm.',
        'end_time.required'        => 'Giờ kết thúc là bắt buộc.',
        'end_time.date_format'     => 'Giờ kết thúc không hợp lệ. Định dạng đúng là HH:mm.',
        'end_time.after'           => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
        'days.required'            => 'Danh sách ngày học là bắt buộc.',
        'days.array'               => 'Danh sách ngày học phải là một mảng.',
        'days.min'                 => 'Lớp học phải có ít nhất một ngày học.',
        'days.*.required'          => 'Ngày học không được để trống.',
        'days.*.regex'             => 'Ngày học phải có định dạng yy/mm/dddd.',
        'number_of_students.required' => 'Số lượng học viên là bắt buộc.',
        'number_of_students.integer'  => 'Số lượng học viên phải là số nguyên.',
        'number_of_students.min'      => 'Số lượng học viên phải lớn hơn hoặc bằng 1.',
        'teacher.required'         => 'ID giáo viên là bắt buộc.',
        'teacher.integer'          => 'ID giáo viên phải là số nguyên.',
        'teacher.exists'           => 'Giáo viên không tồn tại trong hệ thống.'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Dữ liệu nhập vào không hợp lệ',
            'code' => 400,
            'data' => null,
            'meta' => null,
            'message_array' => $validator->errors()
        ], 400);
    }


    try {
        $validatedDays = array_map(function ($day) {
            return \Carbon\Carbon::createFromFormat('d-m-Y', $day)->format('d-m-Y');
        }, $request->days);
        
        $class = Classes::create([
            'class_code'         => $request->class_code,
            'class_type'         => $request->class_type,
            'start_date'         => $request->start_date,
            'end_date'           => $request->end_date,
            'start_time'         => $request->start_time,
            'end_time'           => $request->end_time,
            'days'               => implode(',', $validatedDays), // Chuyển thành "dd/mm/yyyy,dd/mm/yyyy"
            'student_count' => $request->number_of_students,
            'teacher_id'         => $request->teacher
        ]);
        return response()->json([
            'message' => 'Lớp học đã được tạo thành công.',
            'code' => 201,
            'data' => (object) [
                'id'                 => $class->id,
                'class_code'         => $class->class_code,
                'class_type'         => $class->class_type,
                'start_date'         => $class->start_date,
                'end_date'           => $class->end_date,
                'start_time'         => $class->start_time,
                'end_time'           => $class->end_time,
                'days'               => explode(',', $class->days), // Chuyển chuỗi thành mảng ["dd/mm/yyyy", "dd/mm/yyyy"]
                'number_of_students' => $class->number_of_students,
                'teacher'            => $class->teacher_id
            ],
            'meta' => null
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Lỗi trong quá trình tạo lớp học',
            'code' => 500,
            'data' => null,
            'meta' => null,
            'error_detail' => $e->getMessage()
        ], 500);
    }
}

    public function listClass(Request $request)
    {
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);
        
        // Truy vấn danh sách lớp, eager load bảng Teacher để lấy tên giáo viên
        $query = Classes::with('teacher')
            ->where('is_deleted', false);

        
        // Phân trang dữ liệu
        $classes = $query->paginate($pageSize, [
            'id', 'class_code', 'class_type', 'start_date', 'end_date', 
            'start_time', 'end_time', 'days', 'student_count', 'teacher_id'
        ], 'page', $pageNumber);

        // Chuyển đổi dữ liệu theo format mong muốn
        $formattedClasses = $classes->map(function ($class) {
            return [
                'id' => $class->id,
                'class_code' => $class->class_code,
                'class_type' => $class->class_type,
                'start_date' => $class->start_date,
                'end_date' => $class->end_date,
                'start_time' => $class->start_time,
                'end_time' => $class->end_time,
                'days' => explode(',', $class->days),
                'number_of_students' => $class->student_count,
                'teacher' => optional($class->teacher)->full_name
            ];
        });

        return response()->json([
            'message' => 'Lấy danh sách lớp thành công',
            'code' => 200,
            'data' => $formattedClasses,
           'meta' => $classes->total() > 0 ? [
                'total' => $classes->total(),
                'pageCurrent' => $classes->currentPage(),
                'pageSize' => $classes->perPage(),
                'totalPage' => $classes->lastPage()
            ] : null
        ], 200);
    }


    public function edit(Request $request, $id)
    {
        // Kiểm tra lớp học tồn tại
        $class = Classes::where('id', $id)->where('is_deleted', false)->first();
        if (!$class) {
            return response()->json([
                'message' => 'Lớp học không tồn tại',
                'code' => 404,
                'data' => null,
                'meta' => null
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'class_code'          => 'required|string|unique:Room,class_code,'.$id,
            'class_type'          => 'required|string|in:Beginner,Toeic A,Toeic B',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i|after:start_time',
            'days'                => 'required|array|min:1',
            'days.*'              => ['required', 'regex:/^\d{2}-\d{2}-\d{4}$/'], // dd-mm-yyyy
            'number_of_students'  => 'required|integer|min:1',
            'teacher'             => 'required|integer|exists:User,id'
        ], [
            'class_code.required'      => 'Mã lớp học là bắt buộc.',
            'class_code.unique'        => 'Mã lớp học đã tồn tại.',
            'class_type.required'      => 'Loại lớp học là bắt buộc.',
            'class_type.in'            => 'Loại lớp học không hợp lệ. Chỉ chấp nhận: Tập sự, Toeic A, Toeic B.',
            'start_date.required'      => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date'          => 'Ngày bắt đầu không hợp lệ.',
            'end_date.required'        => 'Ngày kết thúc là bắt buộc.',
            'end_date.date'            => 'Ngày kết thúc không hợp lệ.',
            'end_date.after_or_equal'  => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'start_time.required'      => 'Giờ bắt đầu là bắt buộc.',
            'start_time.date_format'   => 'Giờ bắt đầu không hợp lệ. Định dạng đúng là HH:mm.',
            'end_time.required'        => 'Giờ kết thúc là bắt buộc.',
            'end_time.date_format'     => 'Giờ kết thúc không hợp lệ. Định dạng đúng là HH:mm.',
            'end_time.after'           => 'Giờ kết thúc phải lớn hơn giờ bắt đầu.',
            'days.required'            => 'Danh sách ngày học là bắt buộc.',
            'days.array'               => 'Danh sách ngày học phải là một mảng.',
            'days.min'                 => 'Lớp học phải có ít nhất một ngày học.',
            'days.*.required'          => 'Ngày học không được để trống.',
            'days.*.regex'             => 'Ngày học phải có định dạng dd-mm-yyyy.',
            'number_of_students.required' => 'Số lượng học viên là bắt buộc.',
            'number_of_students.integer'  => 'Số lượng học viên phải là số nguyên.',
            'number_of_students.min'      => 'Số lượng học viên phải lớn hơn hoặc bằng 1.',
            'teacher.required'         => 'ID giáo viên là bắt buộc.',
            'teacher.integer'          => 'ID giáo viên phải là số nguyên.',
            'teacher.exists'           => 'Giáo viên không tồn tại trong hệ thống.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' => $validator->errors()
            ], 400);
        }

        try {
            $validatedDays = array_map(function ($day) {
                return \Carbon\Carbon::createFromFormat('d-m-Y', $day)->format('d-m-Y');
            }, $request->days);
            
            $class->update([
                'class_code'         => $request->class_code,
                'class_type'         => $request->class_type,
                'start_date'         => $request->start_date,
                'end_date'           => $request->end_date,
                'start_time'         => $request->start_time,
                'end_time'           => $request->end_time,
                'days'               => implode(',', $validatedDays),
                'student_count'      => $request->number_of_students,
                'teacher_id'         => $request->teacher
            ]);

            return response()->json([
                'message' => 'Lớp học đã được cập nhật thành công.',
                'code' => 200,
                'data' => (object) [
                    'id'                 => $class->id,
                    'class_code'         => $class->class_code,
                    'class_type'         => $class->class_type,
                    'start_date'         => $class->start_date,
                    'end_date'           => $class->end_date,
                    'start_time'         => $class->start_time,
                    'end_time'           => $class->end_time,
                    'days'               => explode(',', $class->days),
                    'number_of_students' => $class->student_count,
                    'teacher'            => $class->teacher_id
                ],
                'meta' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi trong quá trình cập nhật lớp học',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

}
