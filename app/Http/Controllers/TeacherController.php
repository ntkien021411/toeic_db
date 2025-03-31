<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Models\Diploma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendPasswordMail;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class TeacherController extends Controller
{
    public function listTeacher(Request $request)
    {
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);
    
        // Truy vấn danh sách giáo viên, Eager Loading để lấy email và chứng chỉ
        $query = User::query()
                ->where('role', 'TEACHER')
                ->where('is_deleted', false)
                ->with([
                    'account:id,email', // Lấy email từ bảng account
                    'diplomas:id,user_id,certificate_name' // Lấy danh sách chứng chỉ từ bảng diploma
                ]);
    
        // Phân trang dữ liệu
        $teachers = $query->paginate($pageSize, ['id','full_name', 'first_name','image_link', 'last_name', 'birth_date', 'gender', 'phone', 'address', 'account_id'], 'page', $pageNumber);
    
        // Chuyển đổi dữ liệu theo format mong muốn
        $formattedTeachers = $teachers->map(function ($teacher) {
            return [
                'id' => $teacher->id,
                'name' => $teacher->full_name,
                'dob' => $teacher->birth_date,
                'gender' => $teacher->gender,
                'phone' => $teacher->phone,
                'email' => optional($teacher->account)->email, // Lấy email từ account (nếu có)
                'address' => $teacher->address,
                'avatar' => $teacher->image_link,
                'certificate' => $teacher->diplomas->map(function ($diploma) {
                    return [
                        'certificate_name' => $diploma->certificate_name,
                        'score' => $diploma->score,
                    ];
                })->toArray()
            ];
        });
    
        return response()->json([
            'message' => 'Lấy danh sách giáo viên thành công',
            'code' => 200,
            'data' => $formattedTeachers,
            'meta' => $teachers->total() > 0 ? [
                'total' => $teachers->total(),
                'pageCurrent' => $teachers->currentPage(),
                'pageSize' => $teachers->perPage(),
                'totalPage' => $teachers->lastPage()
            ] : null
        ], 200);
    }
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'dob' => 'required|date',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:11|unique:User,phone',
            'email' => 'nullable|email|max:100|unique:Account,email',
            'address' => 'nullable|string|max:255',
            'image_link' => 'nullable|string' // Thêm trường image_link
        ], [
            'name.required' => 'Tên không được để trống.',
            'dob.required' => 'Ngày sinh không được để trống.',
            'dob.date' => 'Ngày sinh không hợp lệ.',
            'gender.required' => 'Giới tính không được để trống.',
            'gender.in' => 'Giới tính chỉ được là MALE, FEMALE hoặc OTHER.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'phone.max' => 'Số điện thoại không được vượt quá 15 ký tự.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'address.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
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

        try {
            DB::beginTransaction();

            // Kiểm tra xem email đã tồn tại chưa
            if (!empty($request->email) && Account::where('email', $request->email)->exists()) {
                return response()->json([
                    'message' => 'Email đã tồn tại.',
                    'code' => 400
                ], 400);
            }

            // Kiểm tra xem số điện thoại đã tồn tại chưa
            if (!empty($request->phone) && User::where('phone', $request->phone)->exists()) {
                return response()->json([
                    'message' => 'Số điện thoại đã tồn tại.',
                    'code' => 400
                ], 400);
            }

            // Tạo mật khẩu ngẫu nhiên
            $password = Hash::make(Str::random(12));

            // Tạo username từ email (lấy phần trước @)
            $baseUsername = explode('@', $request->email)[0];
            $username = $baseUsername . Str::random(3);
            $counter = 1;

            // Kiểm tra xem username đã tồn tại chưa
            while (Account::where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }

            // Tạo tài khoản mới
            $account = Account::create([
                'username' => $username,
                'email' => $request->email,
                'password' => $password,
                'active_status' => false,
                'is_first' => true,
                'active_date' => now(),
            ]);

            // Upload ảnh lên Cloudinary nếu có
            $imageUrl = null;
            if (!empty($request->image_link)) {
                // Thêm data URI nếu không có
                if (strpos($request->image_link, 'data:image/') !== 0) {
                    $request->image_link = 'data:image/png;base64,' . $request->image_link;
                }

                // Kiểm tra base64 hợp lệ
                if ($this->isValidBase64($request->image_link)) {
                    $imageUrl = $this->uploadImageToCloudinary($request->image_link);
                    if ($imageUrl === 123) {
                        return response()->json([
                            'message' => 'Không thể upload hình ảnh.',
                            'code' => 400
                        ], 400);
                    }
                } else {
                    return response()->json([
                        'message' => 'Image base64 không hợp lệ.',
                        'code' => 400
                    ], 400);
                }
            }

            // Tạo giáo viên mới
            $teacher = User::create([
                'account_id' => $account->id,
                'full_name' => $request->name,
                'birth_date' => $request->dob,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'address' => $request->address,
                'role' => 'TEACHER',
                'is_deleted' => false,
                'image_link' => $imageUrl // Lưu đường dẫn ảnh
            ]);

            DB::commit();

            Mail::to($account->email)->queue(new SendPasswordMail($account, $password));

            return response()->json([
                'message' => 'Tài khoản và mật khẩu mới đã được gửi vào email của bạn.',
                'notice' => 'Trong trường hợp không nhận được mail trong 2p xin vui lòng kiểm tra lại email đã nhập!',
                'code' => 200,
                'data' => $teacher,
                'meta' => null
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi thêm giáo viên.',
                'code' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer'
        ], [
            'ids.required' => 'Danh sách ID là bắt buộc.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải có ít nhất một ID.',
            'ids.*.integer' => 'ID phải là số nguyên.'
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
    
        try {
            $now = now();
            $requestedIds = $request->ids;
    
            // Lấy danh sách ID có tồn tại
            $existingUsers = User::whereIn('id', $requestedIds)->get();
            $existingIds = $existingUsers->pluck('id')->toArray();
            $invalidIds = array_diff($requestedIds, $existingIds);
    
            // Lọc ra danh sách giáo viên có role TEACHER
            $teachers = $existingUsers->where('role', 'TEACHER');
    
            if ($teachers->isEmpty()) {
                return response()->json([
                    'message' => 'Không tìm thấy giáo viên hợp lệ để xóa.',
                    'code' => 400,
                    'data' => [
                        'invalid_ids' => $invalidIds
                    ],
                    'meta' => null
                ], 400);
            }
    
            // Lấy danh sách account_id từ bảng users để update bảng account
            $accountIds = $teachers->pluck('account_id')->unique()->toArray();
            $userIds = $teachers->pluck('id')->toArray();
    
            // Cập nhật bảng users
            User::whereIn('id', $userIds)->update([
                'is_deleted' => true,
                'deleted_at' => $now
            ]);
    
            // Cập nhật bảng account
            Account::whereIn('id', $accountIds)->update([
                'is_deleted' => true,
                'deleted_at' => $now
            ]);
    
            return response()->json([
                'message' => 'Xóa giáo viên thành công.',
                'code' => 200,
                'data' => [
                    'deleted_users' => $userIds,
                    'updated_accounts' => $accountIds,
                    'invalid_ids' => $invalidIds
                ],
                'meta' => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi trong quá trình xóa giáo viên.',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    public function editUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'dob' => 'required|date',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:11',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:255',
            'image_link' => 'nullable|string' // Thêm trường image_link
        ], [
            'name.required' => 'Tên không được để trống.',
            'dob.required' => 'Ngày sinh không được để trống.',
            'dob.date' => 'Ngày sinh không hợp lệ.',
            'gender.required' => 'Giới tính không được để trống.',
            'gender.in' => 'Giới tính chỉ được là MALE, FEMALE hoặc OTHER.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'phone.max' => 'Số điện thoại không được vượt quá 15 ký tự.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'address.max' => 'Địa chỉ không được vượt quá 255 ký tự.'
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

        try {
            DB::beginTransaction();
            // Tìm User với điều kiện is_deleted = false
            $user = User::where('id', $id)->where('is_deleted', false)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'Không tìm thấy người dùng hoặc người dùng đã bị xóa.',
                    'code' => 404
                ], 404);
            }
            // Kiểm tra nếu role không phải TEACHER
            if ($user->role !== 'TEACHER') {
                return response()->json([
                    'message' => 'Người dùng không có quyền cập nhật thông tin.',
                    'code' => 403
                ], 403);
            }

            // Kiểm tra email đã tồn tại chưa (trừ user hiện tại)
            if (!empty($request->email) && Account::where('email', $request->email)->where('id', '!=', $user->account_id)->exists()) {
                return response()->json([
                    'message' => 'Email đã tồn tại.',
                    'code' => 400
                ], 400);
            }

            // Kiểm tra số điện thoại đã tồn tại chưa (trừ user hiện tại)
            if (!empty($request->phone) && User::where('phone', $request->phone)->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'message' => 'Số điện thoại đã tồn tại.',
                    'code' => 400
                ], 400);
            }

            // Cập nhật User
            $user->update([
                'full_name' => $request->name,
                'birth_date' => $request->dob,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'address' => $request->address
            ]);

            // Upload ảnh lên Cloudinary nếu có
            if (!empty($request->image_link)) {
                // Thêm data URI nếu không có
                if (strpos($request->image_link, 'data:image/') !== 0) {
                    $request->image_link = 'data:image/png;base64,' . $request->image_link;
                }

                // Kiểm tra base64 hợp lệ
                if ($this->isValidBase64($request->image_link)) {
                    $imageUrl = $this->uploadImageToCloudinary($request->image_link);
                    $user->update(['image_link' => $imageUrl]); // Cập nhật đường dẫn ảnh
                } else {
                    return response()->json([
                        'message' => 'Image base64 không hợp lệ.',
                        'code' => 400
                    ], 400);
                }
            }

            // Tìm Account dựa vào account_id từ bảng User
            $account = Account::find($user->account_id);
            if ($account && !empty($request->email)) {
                $account->update([
                    'email' => $request->email
                ]);
            }
            
            DB::commit();

            // Trả về dữ liệu mà không có các trường deleted_at và is_deleted
            return response()->json([
                'message' => 'Cập nhật thông tin người dùng thành công.',
                'data' => [
                    'id' => $user->id,
                    'account_id' => $user->account_id,
                    'full_name' => $user->full_name,
                    'birth_date' => $user->birth_date,
                    'gender' => $user->gender,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'image_link' => $user->image_link,
                    'role' => $user->role,
                    // Không bao gồm deleted_at và is_deleted
                ],
                'code' => 200
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật người dùng.',
                'code' => 500,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function isValidBase64($string)
    {
        // Kiểm tra xem chuỗi có phải là base64 hợp lệ không
        return preg_match('/^data:image\/(\w+);base64,/', $string) && base64_decode(substr($string, strpos($string, ',') + 1), true) !== false;
    }

    private function uploadImageToCloudinary($base64Image)
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
}
