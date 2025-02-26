<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DiplomaController;
use App\Http\Controllers\ClassUserController;

Route::prefix('api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // ✅ Bảo vệ API bằng middleware 
    // voiyLOO7gbnmv9PYQWLTfpNxBRnPynheHWWxXrysDbMDGaa0HKFLvA8tdMt0

    // 0WgqLG2Tc2cRfoXIih9YuYMeVpomogzEiBtiteKwiA6JBeJjDZ8kWORcWwGd
    //Cả User và Admin đều dùng dc 
        
    //Chỉ Admin dùng được
    Route::middleware(['checkAdmin'])->group(function () {
        //Admin Feature CRUD , Searching , Paging
        Route::get('/accounts', [AdminController::class, 'index']); // Xem danh sách hoặc tìm kiếm tài khoản
        Route::post('/accounts', [AdminController::class, 'store']); // Tạo tài khoản
        Route::put('/accounts/{id}', [AdminController::class, 'update']); // Cập nhật hoặc xóa mềm tài khoản
        
        //Teacher Feature CRUD , Searching , Paging
        Route::get('/teachers', [TeacherController::class, 'index']); // Tìm kiếm và xem danh sách giáo viên

        Route::get('/teachers/{id}', [TeacherController::class, 'show']); // Xem chi tiết giáo viên
        Route::post('/teachers', [TeacherController::class, 'store']); // Thêm giáo viên
        Route::put('/teachers/{id}', [TeacherController::class, 'update']); // Chỉnh sửa hoặc xóa mềm giáo viên

        // Lớp học của giáo viên
        Route::get('/class', [ClassController::class, 'index']);  // Xem danh sách lớp học

        Route::post('/class', [ClassController::class, 'store']);  // Tạo lớp học
        Route::get('/class/{id}', [ClassController::class, 'show']); // Xem chi tiết lớp học
        Route::put('/class/{id}', [ClassController::class, 'update']); // Sửa và xóa mềm lớp học


        // Route::post('/classes/{class_id}/students', [ClassUserController::class, 'addStudent']);

        // Bằng cấp của giáo viên
        // Route::get('/diploma', [DiplomaController::class, 'index']);  // Xem danh sách bằng cấp
        // Route::post('/diploma', [DiplomaController::class, 'store']); // Thêm bằng cấp
        // Route::put('/diploma/{id}', [DiplomaController::class, 'update']); // Sửa bằng cấp
        // Route::get('/class/{id}', [DiplomaController::class, 'show']); // Xem chi tiết bằng cấp   
    });
});