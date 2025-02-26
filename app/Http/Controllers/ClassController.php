<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClassStudent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class ClassController extends Controller
{
    // c.1. Tìm kiếm và xem danh sách class  (có phân trang)
    public function index(Request $request)
        {
            $query = ClassStudent::query()
            ->where('is_deleted', false);

        // Lọc theo các tham số từ request
        if ($request->has('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }
        if ($request->has('class_code')) {
            $query->where('class_code', 'like', "%{$request->class_code}%");
        }
        if ($request->has('class_name')) {
            $query->where('class_name', 'like', "%{$request->class_name}%");
        }
        if ($request->has('start_date')) {
            $query->whereDate('start_date', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('end_date', $request->end_date);
        }
        if ($request->has('is_full')) {
            $query->where('is_full', $request->is_full);
        }
        if ($request->has('student_count')) {
            $query->where('student_count', $request->student_count);
        }

        // Phân trang
        $classes = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => $classes->items(),
            'total' => $classes->total(),
            'current_page' => $classes->currentPage(),
            'per_page' => $classes->perPage(),
            'last_page' => $classes->lastPage(),
            'next_page_url' => $classes->nextPageUrl(),
            'prev_page_url' => $classes->previousPageUrl(),
            'first_page_url' => $classes->url(1),
            'last_page_url' => $classes->url($classes->lastPage())
        ]);
    }

    public function show($id)
    {
        $class = ClassStudent::where('id', $id)->where('is_deleted', false)->first();

        if (!$class) {
            return response()->json(['message' => 'Lớp học không tồn tại'], 404);
        }

        return response()->json($class);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'class_code'   => 'required|string|unique:class,class_code',
            'class_name'   => 'required|string',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'teacher_id'   => 'required|integer|exists:user,id'
        ], [
            'class_code.required'  => 'Mã lớp học là bắt buộc.',
            'class_code.unique'    => 'Mã lớp học đã tồn tại.',
            'class_name.required'  => 'Tên lớp học là bắt buộc.',
            'start_date.required'  => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date'      => 'Ngày bắt đầu không hợp lệ.',
            'end_date.required'    => 'Ngày kết thúc là bắt buộc.',
            'end_date.date'        => 'Ngày kết thúc không hợp lệ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'teacher_id.required'  => 'ID giáo viên là bắt buộc.',
            'teacher_id.integer'   => 'ID giáo viên phải là số nguyên.',
            'teacher_id.exists'    => 'Giáo viên không tồn tại trong hệ thống.'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors()
            ], 400);
        }
    
        $class = ClassStudent::create($validator->validated());
    
        return response()->json([
            'message' => 'Lớp học đã được tạo thành công.',
            'data' => $class
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $class = ClassStudent::where('id', $id)->where('is_deleted', false)->first();

        if (!$class) {
            return response()->json(['message' => 'Lớp học không tồn tại'], 404);
        }

        $validator = Validator::make($request->all(), [
            'class_code'   => 'sometimes|string|unique:class,class_code,' . $id,
            'class_name'   => 'sometimes|string',
            'start_date'   => 'sometimes|date',
            'end_date'     => 'sometimes|date|after_or_equal:start_date',
            'is_deleted'   => 'sometimes|boolean'
        ], [
            'class_code.unique'    => 'Mã lớp học đã tồn tại.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors()
            ], 400);
        }

        $validatedData = $validator->validated();

        if ($request->has('is_deleted') && $request->is_deleted == true) {
            $validatedData['deleted_at'] = now();
            $validatedData['is_deleted'] = true;
        }

        $class->update($validatedData);

        return response()->json([
            'message' => 'Lớp học đã được cập nhật thành công.',
            'data' => $class
        ]);
    }

}
