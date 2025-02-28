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
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DiplomaController;
use App\Http\Controllers\ClassUserController;
use App\Http\Controllers\ExamResultController;

Route::prefix('api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
    // ✅ Bảo vệ API bằng middleware 
    //Chỉ Admin dùng được
    Route::middleware(['checkAdmin'])->group(function () {
        //Tài khoản
        Route::get('/accounts', [AdminController::class, 'index']); // Xem danh sách hoặc tìm kiếm tài khoản
        Route::post('/accounts', [AdminController::class, 'store']); // Tạo tài khoản
        Route::put('/accounts/{id}', [AdminController::class, 'update']); // Cập nhật hoặc xóa mềm tài khoản
        
        //Giáo viên
        Route::get('/teachers', [TeacherController::class, 'index']); // Tìm kiếm và xem danh sách giáo viên
        Route::get('/teachers/{id}', [TeacherController::class, 'show']); // Xem chi tiết giáo viên
        Route::post('/teachers', [TeacherController::class, 'store']); // Thêm giáo viên
        Route::put('/teachers/{id}', [TeacherController::class, 'update']); // Chỉnh sửa hoặc xóa mềm giáo viên

        // Lớp học của giáo viên
        Route::get('/class', [ClassController::class, 'index']);  // Xem danh sách tất cả lớp học hoặc theo giáo viên
        Route::post('/class', [ClassController::class, 'store']);  // Tạo lớp học
        Route::get('/class/{id}', [ClassController::class, 'show']); // Xem chi tiết lớp học
        Route::put('/class/{id}', [ClassController::class, 'update']); // Sửa và xóa mềm lớp học

        //Học viên
        Route::get('/students', [StudentController::class, 'index']); // Tìm kiếm và xem danh sách học viên
        Route::get('/students/{id}', [StudentController::class, 'show']); // Xem chi tiết học viên
        Route::post('/students', [StudentController::class, 'store']); // Thêm học viên
        Route::put('/students/{id}', [StudentController::class, 'update']); // Chỉnh sửa hoặc xóa mềm học viên

        //Thêm học viên vào lớp
        Route::post('/classes/students', [ClassUserController::class, 'addStudentToClass']);//Thêm học sinh vào lớp học
        //Xem lớp học của học viên
        Route::get('/classes/students/{user_id}', [ClassUserController::class, 'getStudentClasses']);//Xem lớp học của học viên

        // Bằng cấp của giáo viên
        Route::get('/diploma', [DiplomaController::class, 'index']);  // Xem danh sách tất cả bằng cấp hoặc của 1 giáo viên
        Route::post('/diploma', [DiplomaController::class, 'store']); // Thêm bằng cấp
        Route::put('/diploma/{id}', [DiplomaController::class, 'update']); // Sửa và xóa mềm bằng cấp
        Route::get('/diploma/{id}', [DiplomaController::class, 'show']); // Xem chi tiết bằng cấp   

        //Lịch sử làm bài thi của học viên 
        Route::get('/exam-results/history/{user_id}', [ExamResultController::class, 'examHistory']); //Xem danh sách lịch sử bài thi của học viên
        Route::get('/exam-results/search', [ExamResultController::class, 'searchExamResults']); //Tìm kiếm bài thi học viên đã làm
        Route::get('/exam-results/detail/{exam_id}', [ExamResultController::class, 'examDetail']); //Xem thông tin chi tiết bài thi
        //Route::get('/exam-results/analysis/{user_id}', [ExamResultController::class, 'analyzePerformance']); 
        
    });
});