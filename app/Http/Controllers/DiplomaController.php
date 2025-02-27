<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Diploma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DiplomaController extends Controller
{
    // Lấy tất cả bằng cấp hoặc tìm kiếm theo giáo viên và các tiêu chí khác
    // /diploma?user_id=2&certificate_name=TOEFL&score=90
    public function index(Request $request)
    {
        $query = Diploma::where('is_deleted', false);

        // Kiểm tra xem user_id có tồn tại không
        if ($request->has('user_id')) {
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'message' => 'Teacher không tồn tại.',
                    'code' => 404,
                    'data' => null,
                    'meta' => null
                ], 404);
            }
            
            // Kiểm tra role có phải TEACHER không
            if ($user->role !== 'TEACHER') {
                return response()->json([
                    'message' => 'User không phải là giáo viên.',
                    'code' => 403,
                    'data' => null,
                    'meta' => null
                ], 403);
            }

            $query->where('user_id', $request->user_id);
        }

        // Lọc theo các tiêu chí tìm kiếm khác
        if ($request->has('certificate_name')) {
            $query->where('certificate_name', 'like', "%{$request->certificate_name}%");
        }
        if ($request->has('score')) {
            $query->where('score', $request->score);
        }
        if ($request->has('level')) {
            $query->where('level', 'like', "%{$request->level}%");
        }
        if ($request->has('issued_by')) {
            $query->where('issued_by', 'like', "%{$request->issued_by}%");
        }
        if ($request->has('issue_date')) {
            $query->whereDate('issue_date', $request->issue_date);
        }
        if ($request->has('expiry_date')) {
            $query->whereDate('expiry_date', $request->expiry_date);
        }

        // Lấy kết quả
        $diplomas = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'message' => 'Danh sách tất cả bằng cấp.',
            'code' => 200,
            'data' => $diplomas->items(),
            'meta' => $diplomas->total() > 0 ?[
                'total' => $diplomas->total(),
                'current_page' => $diplomas->currentPage(),
                'per_page' => $diplomas->perPage(),
                'last_page' => $diplomas->lastPage(),
                'next_page_url' => $diplomas->nextPageUrl(),
                'prev_page_url' => $diplomas->previousPageUrl(),
                'first_page_url' => $diplomas->url(1),
                'last_page_url' => $diplomas->url($diplomas->lastPage())
            ] : null
        ], 200);
    }

     // Lấy chi tiết chứng chỉ
     public function show($id)
     {
         $diploma = Diploma::where('id', $id)->where('is_deleted', false)->first();
 
         if (!$diploma) {
             return response()->json([
                 'message' => 'Chứng chỉ không tồn tại.',
                 'code' => 404,
                 'data' => null,
                 'meta' => null
             ], 404);
         }
 
         return response()->json([
             'message' => 'Chi tiết chứng chỉ.',
             'code' => 200,
             'data' => $diploma,
             'meta' => null
         ], 200);
     }

     // Thêm mới chứng chỉ cho giáo viên
     public function store(Request $request)
     {
         $validator = Validator::make($request->all(), [
             'user_id' => 'required|exists:user,id',
             'certificate_name' => 'required|string|max:255',
             'score' => 'required|numeric|min:0',
             'level' => 'required|string|max:500',
             'issued_by' => 'required|string|max:255',
             'issue_date' => 'required|date',
             'expiry_date' => 'nullable|date|after_or_equal:issue_date',
             'certificate_image' => 'nullable|string',
         ], [
             'user_id.required' => 'User ID là bắt buộc.',
             'user_id.exists' => 'User không tồn tại.',
             'certificate_name.required' => 'Tên chứng chỉ là bắt buộc.',
             'certificate_name.string' => 'Tên chứng chỉ phải là chuỗi.',
             'score.required' => 'Điểm số là bắt buộc.',
             'score.numeric' => 'Điểm số phải là số.',
             'level.required' => 'Cấp độ là bắt buộc.',
             'level.string' => 'Cấp độ phải là chuỗi.',
             'issued_by.required' => 'Nơi cấp là bắt buộc.',
             'issued_by.string' => 'Nơi cấp phải là chuỗi.',
             'issue_date.required' => 'Ngày cấp là bắt buộc.',
             'issue_date.date' => 'Ngày cấp phải là ngày hợp lệ.',
             'expiry_date.date' => 'Ngày hết hạn phải là ngày hợp lệ.',
             'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày cấp.',
         ]);
 
         if ($validator->fails()) {
             return response()->json([
                 'message' => 'Dữ liệu nhập vào không hợp lệ.',
                 'code' => 400,
                 'data' => null,
                 'meta' => null,
                 'message_array' => $validator->errors()
             ], 400);
         }
 
         $user = User::find($request->user_id);
         if ($user->role !== 'TEACHER') {
             return response()->json([
                 'message' => 'User không phải là giáo viên.',
                 'code' => 403,
                 'data' => null,
                 'meta' => null
             ], 403);
         }
 
         $diploma = Diploma::create($request->all());
 
         return response()->json([
             'message' => 'Thêm chứng chỉ thành công.',
             'code' => 201,
             'data' => $diploma,
             'meta' => null
         ], 201);
     }
 
     // Cập nhật chứng chỉ của giáo viên
     public function update(Request $request, $id)
     {
         $diploma = Diploma::find($id);
         if (!$diploma || $diploma->is_deleted) {
             return response()->json([
                 'message' => 'Chứng chỉ không tồn tại.',
                 'code' => 404,
                 'data' => null,
                 'meta' => null
             ], 404);
         }
 
         $validator = Validator::make($request->all(), [
            'certificate_name' => 'sometimes|string|max:255',
            'score' => 'sometimes|numeric|min:0',
            'level' => 'sometimes|string|max:500',
            'issued_by' => 'sometimes|string|max:255',
            'issue_date' => 'sometimes|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'certificate_image' => 'nullable|string',
        ], [
            'certificate_name.string' => 'Tên chứng chỉ phải là chuỗi ký tự.',
            'certificate_name.max' => 'Tên chứng chỉ không được vượt quá 255 ký tự.',
            'score.numeric' => 'Điểm phải là số.',
            'score.min' => 'Điểm không thể nhỏ hơn 0.',
            'level.string' => 'Cấp độ phải là chuỗi ký tự.',
            'level.max' => 'Cấp độ không được vượt quá 500 ký tự.',
            'issued_by.string' => 'Cấp bởi phải là chuỗi ký tự.',
            'issued_by.max' => 'Cấp bởi không được vượt quá 255 ký tự.',
            'issue_date.date' => 'Ngày cấp phải là định dạng ngày hợp lệ.',
            'expiry_date.date' => 'Ngày hết hạn phải là định dạng ngày hợp lệ.',
            'expiry_date.after_or_equal' => 'Ngày hết hạn phải sau hoặc bằng ngày cấp.',
            'certificate_image.string' => 'Hình ảnh chứng chỉ phải là chuỗi.',
        ]);
        
         if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ.',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' => $validator->errors()
            ], 400);
         }
         $validatedData = $validator->validated();

         if ($request->has('is_deleted') && $request->is_deleted == true) {
             $validatedData['deleted_at'] = now();
             $validatedData['is_deleted'] = true;
         }
 
         $diploma->update($validatedData);
 
         return response()->json([
             'message' => 'Cập nhật chứng chỉ thành công.',
             'code' => 200,
             'data' => $diploma,
             'meta' => null
         ], 200);
     }


}
