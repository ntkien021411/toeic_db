<?php
use Illuminate\Http\Request;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Models\User;

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
        'message' => 'Ứng dụng đã chạy thành công!',
        'status' => true
    ]);
});
Route::get('/users', function () {
    return response()->json(User::all());
});

Route::prefix('api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });
});
