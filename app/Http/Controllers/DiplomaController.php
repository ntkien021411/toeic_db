<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Diploma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class DiplomaController extends Controller
{

    private function isValidBase64($string)
    {
        // Kiểm tra xem chuỗi có phải là base64 hợp lệ không
        return preg_match('/^data:image\/(\w+);base64,/', $string) && base64_decode(substr($string, strpos($string, ',') + 1), true) !== false;
    }

    private function uploadToCloudinary($base64Image)
    {
        try {
            // Thêm data URI nếu không có
            if (strpos($base64Image, 'data:image/') !== 0) {
                $base64Image = 'data:image/png;base64,' . $base64Image;
            }

            // Kiểm tra base64 hợp lệ
            if ($this->isValidBase64($base64Image)) {
                $result = Cloudinary::upload($base64Image, [
                    'resource_type' => 'image',
                    'folder' => 'images',
                    'timeout' => 300 // 5 minutes timeout
                ]);
                return $result->getSecurePath();; // Trả về URL của ảnh đã upload
            } else {
                throw new \Exception('Hình ảnh base64 không hợp lệ.');
            }
        } catch (\Exception $e) {
            // Nếu có lỗi xảy ra, trả về 123
            return 123;
        }
    }
    public function index($user_id, Request $request)
    {
        // Lấy số trang và kích thước trang từ request
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);

        // Truy vấn danh sách chứng chỉ theo user_id với phân trang
        $diplomas = Diploma::where('user_id', $user_id)
            ->select('id', 'user_id', 'certificate_name', 'score', 'level', 'issued_by', 'issue_date', 'expiry_date', 'certificate_image') // Chỉ lấy các trường cần thiết
            ->paginate($pageSize, ['*'], 'page', $pageNumber);

        // Chuyển đổi dữ liệu để loại bỏ các trường không cần thiết
        $formattedDiplomas = $diplomas->map(function ($diploma) {
            return [
                'id' => $diploma->id,
                'user_id' => $diploma->user_id,
                'certificate_name' => $diploma->certificate_name,
                'score' => $diploma->score,
                'level' => $diploma->level,
                'issued_by' => $diploma->issued_by,
                'issue_date' => $diploma->issue_date,
                'expiry_date' => $diploma->expiry_date,
                'certificate_image' => $diploma->certificate_image,
            ];
        });

        return response()->json([
            'message' => 'Lấy danh sách chứng chỉ thành công.',
            'code' => 200,
            'data' => $formattedDiplomas, // Trả về danh sách chứng chỉ đã được định dạng
            'meta' => [
                'total' => $diplomas->total(),
                'pageCurrent' => $diplomas->currentPage(),
                'pageSize' => $diplomas->perPage(),
                'totalPage' => $diplomas->lastPage(),
            ]
        ], 200);
    }

     // Thêm mới chứng chỉ cho giáo viên
     public function store(Request $request)
     {
         $validator = Validator::make($request->all(), [
             'user_id' => 'required|exists:User,id',
             'certificate_name' => 'required|string|max:255',
             'score' => 'required|numeric|min:0',
             'level' => 'nullable|string|max:500',
             'issued_by' => 'nullable|string|max:255',
             'issue_date' => 'nullable|date',
             'expiry_date' => 'nullable|date|after_or_equal:issue_date',
             'certificate_image' => 'nullable|string',
         ], [
             'user_id.required' => 'User ID là bắt buộc.',
             'user_id.exists' => 'User không tồn tại.',
             'certificate_name.required' => 'Tên chứng chỉ là bắt buộc.',
             'certificate_name.string' => 'Tên chứng chỉ phải là chuỗi.',
             'score.required' => 'Điểm số là bắt buộc.',
             'score.numeric' => 'Điểm số phải là số.',
             'level.string' => 'Cấp độ phải là chuỗi.',
             'issued_by.string' => 'Nơi cấp phải là chuỗi.',
             'issue_date.date' => 'Ngày cấp phải là định dạng ngày hợp lệ.',
             'expiry_date.date' => 'Ngày hết hạn phải là định dạng ngày hợp lệ.',
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
 
         // Upload ảnh lên Cloudinary nếu có
         $certificateImageUrl = null;
         if (!empty($request->certificate_image)) {
             // Thêm data URI nếu không có
             if (strpos($request->certificate_image, 'data:image/') !== 0) {
                 $request->certificate_image = 'data:image/png;base64,' . $request->certificate_image;
             }

             // Kiểm tra base64 hợp lệ
             if ($this->isValidBase64($request->certificate_image)) {
                 $certificateImageUrl = $this->uploadToCloudinary($request->certificate_image);
             } else {
                 return response()->json([
                     'message' => 'Hình ảnh chứng chỉ không hợp lệ.',
                     'code' => 400
                 ], 400);
             }
         }

         // Tạo chứng chỉ mới
         $diplomaData = $request->only(['user_id', 'certificate_name', 'score', 'level', 'issued_by', 'issue_date', 'expiry_date']);
         $diplomaData['certificate_image'] = $certificateImageUrl; // Lưu đường dẫn ảnh

         $diploma = Diploma::create($diplomaData);
 
         return response()->json([
             'message' => 'Thêm chứng chỉ thành công.',
             'code' => 201,
             'data' => [
                 'id' => $diploma->id,
                 'user_id' => $diploma->user_id,
                 'certificate_name' => $diploma->certificate_name,
                 'score' => $diploma->score,
                 'level' => $diploma->level,
                 'issued_by' => $diploma->issued_by,
                 'issue_date' => $diploma->issue_date,
                 'expiry_date' => $diploma->expiry_date,
                 'certificate_image' => $diploma->certificate_image,
             ],
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
            'certificate_name' => 'sometimes|required|string|max:255',
            'score' => 'sometimes|required|numeric|min:0',
            'level' => 'nullable|string|max:500',
            'issued_by' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'certificate_image' => 'nullable|string',
        ], [
            'certificate_name.required' => 'Tên chứng chỉ là bắt buộc.',
            'certificate_name.string' => 'Tên chứng chỉ phải là chuỗi ký tự.',
            'certificate_name.max' => 'Tên chứng chỉ không được vượt quá 255 ký tự.',
            'score.required' => 'Điểm là bắt buộc.',
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

         // Upload ảnh lên Cloudinary nếu có
         if (!empty($request->certificate_image)) {
             // Thêm data URI nếu không có
             if (strpos($request->certificate_image, 'data:image/') !== 0) {
                 $request->certificate_image = 'data:image/png;base64,' . $request->certificate_image;
             }

             // Kiểm tra base64 hợp lệ
             if ($this->isValidBase64($request->certificate_image)) {
                 $validatedData['certificate_image'] = $this->uploadToCloudinary($request->certificate_image);
             } else {
                 return response()->json([
                     'message' => 'Hình ảnh chứng chỉ không hợp lệ.',
                     'code' => 400
                 ], 400);
             }
         }

         if ($request->has('is_deleted') && $request->is_deleted == true) {
             $validatedData['deleted_at'] = now();
             $validatedData['is_deleted'] = true;
         }
 
         $diploma->update($validatedData);
 
         return response()->json([
             'message' => 'Cập nhật chứng chỉ thành công.',
             'code' => 200,
             'data' => [
                 'id' => $diploma->id,
                 'user_id' => $diploma->user_id,
                 'certificate_name' => $diploma->certificate_name,
                 'score' => $diploma->score,
                 'level' => $diploma->level,
                 'issued_by' => $diploma->issued_by,
                 'issue_date' => $diploma->issue_date,
                 'expiry_date' => $diploma->expiry_date,
                 'certificate_image' => $diploma->certificate_image,
             ],
             'meta' => null
         ], 200);
     }

    public function softDelete(Request $request)
    {
        // Lấy danh sách ID từ request
        $ids = $request->input('ids');

        // Tìm các ID tồn tại trong cơ sở dữ liệu
        $existingDiplomas = Diploma::whereIn('id', $ids)->pluck('id')->toArray();

        // Cập nhật trạng thái is_deleted và deleted_at cho các chứng chỉ
        Diploma::whereIn('id', $existingDiplomas)->update([
            'is_deleted' => true,
            'deleted_at' => now()
        ]);

        // Tìm các ID không tồn tại
        $nonExistingIds = array_diff($ids, $existingDiplomas);

        // Trả về danh sách ID đã được xử lý và ID không tồn tại
        return response()->json([
            'message' => 'Xóa mềm chứng chỉ thành công.',
            'code' => 200,
            'data' => [
                'updated_ids' => array_values($existingDiplomas), // Trả về ID đã được cập nhật mà không có chỉ số
                'non_existing_ids' => array_values($nonExistingIds) // Trả về ID không tồn tại mà không có chỉ số
            ]
        ], 200);
    }

}
