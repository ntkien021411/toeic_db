<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;


class AdminController extends Controller
{
    public function listAccount(Request $request)
    {
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);

        // Truy vấn danh sách tài khoản
        $query = Account::query()->where('is_deleted', false);

        // Phân trang dữ liệu
        $accounts = $query->paginate($pageSize, ['id', 'username', 'email','active_status', 'active_date', 'is_first'], 'page', $pageNumber);

        // Chuyển đổi dữ liệu theo format mong muốn
        $formattedAccounts = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'username' => $account->username,
                'email' => $account->email,
                'role' => $account->user->role,
                'active_status' => (bool) $account->active_status,
                'active_date' => (bool) $account->active_date,
                'is_first' => isset($account->is_first) ? (bool) $account->is_first : null
            ];
        });

        return response()->json([
            'message' => 'Lấy danh sách tài khoản thành công',
            'code' => 200,
            'data' => $formattedAccounts,
            'meta' => $accounts->total() > 0 ? [
                'total' => $accounts->total(),
                'pageCurrent' => $accounts->currentPage(),
                'pageSize' => $accounts->perPage(),
                'totalPage' => $accounts->lastPage()
            ] : null
        ], 200);
    }


    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        if (!$account) {
            return response()->json([
                'message' => 'Không tìm thấy tài khoản',
                'code' => 404,
                'data' => null,
                'meta' => null
            ], 404);
        }
    
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|unique:account,email,' . $id,
            'active_status' => 'nullable|boolean',
            'is_first' => 'nullable|boolean',
            'is_deleted' => 'nullable|boolean'
        ], [
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'active_status.boolean' => 'Trạng thái phải là true hoặc false.',
            'is_first.boolean' => 'Giá trị is_first phải là true hoặc false.',
            'is_deleted.boolean' => 'is_deleted phải là true hoặc false.'
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
    
        // Chỉ lấy các trường có trong request để cập nhật
        $data = $request->only(['email', 'active_status', 'is_first']);

        // Kiểm tra nếu is_deleted được truyền vào
        if ($request->has('is_deleted')) {
            $isDeleted = filter_var($request->input('is_deleted'), FILTER_VALIDATE_BOOLEAN);

            if ($isDeleted) {
                // Nếu is_deleted = true, đặt ngày xóa
                $data['deleted_at'] = now();
                $data['active_status'] = false;
            } else {
                // Nếu is_deleted = false, xóa deleted_at (khôi phục tài khoản)
                $data['deleted_at'] = null;
            }

            $data['is_deleted'] = $isDeleted;
        }

         // Kiểm tra nếu active_status được truyền vào
        if ($request->has('active_status')) {
            $isActive = filter_var($request->input('active_status'), FILTER_VALIDATE_BOOLEAN);

            if ($isActive) {
                // Nếu active_status = true, đặt active_date = now()
                $data['active_date'] = now();
            }
        }

        // Cập nhật tài khoản
        $account->update($data);
    
        return response()->json([
            'message' => 'Cập nhật tài khoản thành công!',
            'code' => 200,
            'data' => $account,
            'meta' => null
        ], 200);
    }
    public function delete(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer'
            ], [
                'ids.required' => 'Danh sách ID  là bắt buộc.',
                'ids.array' => 'Danh sách ID  phải là một mảng.',
                'ids.min' => 'Phải có ít nhất một ID .',
                'ids.*.integer' => 'ID  phải là số nguyên.'
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
                $requestedAccountIds = $request->ids;

                // Lấy danh sách tài khoản hợp lệ
                $existingAccounts = Account::whereIn('id', $requestedAccountIds)->get();
                $validAccountIds = $existingAccounts->pluck('id')->toArray();
                $invalidAccountIds = array_values(array_diff($requestedAccountIds, $validAccountIds));

                if (empty($validAccountIds)) {
                    return response()->json([
                        'message' => 'Không tìm thấy tài khoản hợp lệ để xóa.',
                        'code' => 400,
                        'data' => [
                            'invalid_account_ids' => $invalidAccountIds
                        ],
                        'meta' => null
                    ], 400);
                }

                // Lấy danh sách user có account_id hợp lệ nhưng KHÔNG phải ADMIN
                $users = User::whereIn('account_id', $validAccountIds)
                    ->where('role', '!=', 'ADMIN')
                    ->get();
                
                $userIds = $users->pluck('id')->toArray();
                $validUserAccountIds = $users->pluck('account_id')->unique()->toArray();

                // **Chỉ cập nhật tài khoản nếu tài khoản có user hợp lệ (không phải ADMIN)**
                if (!empty($validUserAccountIds)) {
                    Account::whereIn('id', $validUserAccountIds)->update([
                        'is_deleted' => true,
                        'deleted_at' => $now
                    ]);
                }

                // Nếu không có user nào hợp lệ (chỉ có ADMIN hoặc không có user nào)
                if (empty($userIds)) {
                    return response()->json([
                        'message' => 'Không có user nào cần xóa (chỉ có ADMIN hoặc không tồn tại).',
                        'code' => 400,
                        'data' => [
                            'updated_accounts' => $validUserAccountIds,
                            'invalid_account_ids' => $invalidAccountIds
                        ],
                        'meta' => null
                    ], 400);
                }

                // Cập nhật bảng `users`
                User::whereIn('id', $userIds)->update([
                    'is_deleted' => true,
                    'deleted_at' => $now
                ]);

                return response()->json([
                    'message' => 'Xóa account thành công.',
                    'code' => 200,
                    'data' => [
                        'deleted_users' => $userIds,
                        'updated_accounts' => $validUserAccountIds,
                        'invalid_account_ids' => $invalidAccountIds
                    ],
                    'meta' => null
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Lỗi trong quá trình xóa account.',
                    'code' => 500,
                    'data' => null,
                    'meta' => null,
                    'error_detail' => $e->getMessage()
                ], 500);
            }
        }




}
