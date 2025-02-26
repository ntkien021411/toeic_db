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
                $query->where('user.first_name', 'like', "%{$request->first_name}%");
            }
            if ($request->has('last_name')) {
                $query->where('user.last_name', 'like', "%{$request->last_name}%");
            }
            if ($request->has('phone')) {
                $query->where('user.phone', 'like', "%{$request->phone}%");
            }
            if ($request->has('facebook_link')) {
                $query->where('user.facebook_link', 'like', "%{$request->facebook_link}%");
            }
            if ($request->has('birth_date')) {
                $query->where('user.birth_date', 'like', "%{$request->birth_date}%");
            }
            if ($request->has('gender')) {
                $query->where('user.gender', $request->gender);
            }
        
            $teachers = $query->select([
                'user.id', 'user.first_name', 'user.last_name', 'user.phone',
                'user.image_link', 'user.facebook_link', 'user.birth_date', 'user.gender'
            ])->paginate($request->input('per_page', 10));
        

        return response()->json([
            'data' => $teachers->items(),
            'total' => $teachers->total(),
            'current_page' => $teachers->currentPage(),
            'per_page' => $teachers->perPage(),
            'last_page' => $teachers->lastPage(),
            'next_page_url' => $teachers->nextPageUrl(),
            'prev_page_url' => $teachers->previousPageUrl(),
            'first_page_url' => $teachers->url(1),
            'last_page_url' => $teachers->url($teachers->lastPage())
        ]);
    }

       // c.2 Xem thông tin chi tiết của giáo viên
        public function show($id)
        {
            $teacher = User::where('role', 'TEACHER')->where('id', $id)->where('is_deleted', false)->first();
            if (!$teacher) {
                return response()->json(['message' => 'Không tìm thấy giáo viên.'], 404);
            }
            return response()->json($teacher);
        }

    // c.3. Thêm giáo viên
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:account,username',
            'password' => 'required|string|min:6',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'birth_date' => 'required|date',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:15|unique:user,phone',
            'image_link' => 'nullable|url',
            'facebook_link' => 'nullable|url'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 400);
        }

        $account = Account::create([
            'username' => $request->username,
            'password' => Hash::make($request->password)
        ]);

        $teacher = User::create(array_merge(
            $request->only(['first_name', 'last_name', 'birth_date', 'gender', 'phone', 'image_link', 'facebook_link']),
            ['account_id' => $account->id, 'role' => 'TEACHER', 'is_deleted' => false]
        ));

        return response()->json(['message' => 'Giáo viên đã được thêm thành công', 'data' => $teacher], 201);
    }

    // c.4. Chỉnh sửa thông tin giáo viên
    public function update(Request $request, $id)
    {
        $teacher = User::where('id', $id)->where('role', 'TEACHER')->where('is_deleted', false)->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:MALE,FEMALE,OTHER',
            'phone' => 'nullable|string|max:15|unique:user,phone,' . $id,
            'image_link' => 'nullable|url',
            'facebook_link' => 'nullable|url',
            'is_deleted' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
    
        if ($request->has('is_deleted') && $request->is_deleted == true) {
            $teacher->update(['deleted_at' => now()]);
            $teacher->update(['is_deleted' => true]);
        } else {
            $teacher->update($request->only([
                'first_name', 'last_name', 'birth_date', 'gender', 'phone', 'image_link', 'facebook_link'
            ]));
        }
        
        return response()->json(['message' => 'Thông tin giáo viên đã được cập nhật', 'data' => $teacher]);
    }

    

}
