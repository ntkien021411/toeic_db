<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendPasswordMail;
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
                'pageCurrent' => $students->currentPage(),
                'pageSize' => $students->perPage(),
                'totalPage' => $students->lastPage()
            ] : null
        ], 200);
    }
    
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'dob' => 'required|date',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:15|unique:User,phone',
            'email' => 'nullable|email|max:100|unique:Account,email',
            'address' => 'nullable|string|max:255'
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
             $password = Str::random(12);
             $hashedPassword = Hash::make($password);
            
            // Create Account
            $account = Account::create([
                'username' => $username,
                'email' => $request->email,
                'password' => $hashedPassword,
                'active_status' => true,
                'is_first' => true,
                'active_date' => now(),
            ]);
            
            // Create User
            $user = User::create([
                'account_id' => $account->id,
                'full_name' => $request->name,
                'birth_date' => $request->dob,
                'gender' => $request->gender,
                'phone' => $request->phone,
                'address' => $request->address,
                'role' => 'STUDENT',
                'is_deleted' => false
            ]);
            
            DB::commit();
            // Gửi email sau khi commit để tránh rollback không cần thiết
                // Gửi email chứa tài khoản mật khẩu mới (Dùng queue để gửi không bị chậm)
                try {
                    Mail::to($account->email)->queue(new SendPasswordMail($account, $password));

                    return response()->json([
                        'message' => 'Tài khoản và mật khẩu mới đã được gửi vào email của bạn',
                        'notice' => 'Trong trường hợp không nhận được mail trong 2p xin vui lòng kiểm tra lại email đã nhập!',
                        'code' => 200,
                        'data' => [
                            'email' => $email
                        ],
                    ], 200);
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Không thể gửi email. Vui lòng thử lại sau',
                        'code' => 500,
                        'data' => null,
                    ], 500);
                }


            return response()->json([
                'message' => 'Học sinh đã được tạo thành công.',
                'code' => 201,
                'data' => $user,
                'meta' => null
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo học sinh.',
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
            $teachers = $existingUsers->where('role', 'STUDENT');
    
            if ($teachers->isEmpty()) {
                return response()->json([
                    'message' => 'Không tìm thấy học viên hợp lệ để xóa.',
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
                'message' => 'Xóa học viên thành công.',
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
                'message' => 'Lỗi trong quá trình xóa học viên.',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

}
