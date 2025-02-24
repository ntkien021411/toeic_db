<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use Illuminate\Support\Facades\DB;


Route::get('/check-db', function () {
    try {
        // Kết nối database
        $pdo = DB::connection()->getPdo();

        // Lấy tên database hiện tại
        $databaseName = DB::connection()->getDatabaseName();

        // Lấy danh sách tất cả các bảng
        $tables = DB::select('SHOW TABLES');

        // Định dạng danh sách bảng
        $tableList = [];
        foreach ($tables as $table) {
            $tableList[] = array_values((array)$table)[0];
        }

        return response()->json([
            'message' => 'Kết nối database thành công!',
            'database' => $databaseName,
            'tables' => $tableList,
            'status' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Không thể kết nối database!',
            'error' => $e->getMessage(),
            'status' => false
        ], 500);
    }
});
Route::get('/status', function () {
    return response()->json([
        'message' => 'Ứng dụng đã chạy thành công!12321',
        'status' => true
    ]);
});

Route::prefix('api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // ✅ Bảo vệ API bằng middleware require.token
    Route::middleware(['require.token'])->group(function () {
        Route::get('/protected', function () {
            return response()->json(['message' => 'Bạn đã truy cập API thành công!']);
        });
    });
});