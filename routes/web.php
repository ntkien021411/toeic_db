<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\DB;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;


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
use App\Http\Controllers\ExamSectionController;
use App\Http\Controllers\ExcelController;

Route::prefix('api')->group(function () {

    Route::middleware(['checkToken'])->group(function () {
        Route::post('/upload', function (Request $request) {
            return response()->json($request->uploaded_urls);
        })->middleware('upload.image');


        //EXAMSECTION Bài luyện thi toeic 
        Route::get('/tests-full/list', [ExamSectionController::class, 'listExam']); // Xem danh sách bài Luyện thi
    });

   
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        // Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/', [AuthController::class, 'checkAccount']);
        Route::get('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });
    // ✅ Bảo vệ API bằng middleware 
    //Chỉ Admin dùng được
    Route::middleware(['checkAdmin'])->group(function () {

        //TEACHER Xem danh sách giáo viên 
        Route::get('/teachers/list', [TeacherController::class, 'listTeacher']); 
        Route::post('/teachers/add-teacher', [TeacherController::class, 'createUser']); // Thêm giáo viên
        Route::put('/teachers/edit-teacher/{id}', [TeacherController::class, 'editUser']); // Thêm giáo viên
        Route::delete('/teachers/delete', [TeacherController::class, 'delete']); 
        //STUDENT Xem danh sách học viên
        Route::get('/students/list', [StudentController::class, 'listStudent']);
        Route::post('/students/add-student', [StudentController::class, 'createUser']); // Thêm học viên
        Route::put('/students/edit-student/{id}', [StudentController::class, 'editUser']); // Thêm học viên
        Route::delete('/students/delete', [StudentController::class, 'delete']); 

        //ACCOUNT Xem danh sách tài khoản
        Route::get('/users/list', [AdminController::class, 'listAccount']);
        Route::patch('/users/update-status-user', [AdminController::class, 'updateStatus']);
        Route::delete('/users/delete', [AdminController::class, 'delete']); 

        //CLASS Thêm lớp học 
        Route::post('/class', [ClassController::class, 'store']);  // Tạo lớp học
        Route::put('/class/edit-class/{id}', [ClassController::class, 'edit']);  // Tạo lớp học
        //Danh sách lớp học 
        Route::get('/classes/list', [ClassController::class, 'listClass']); 
        //Chi tiết lớp học
        Route::get('/classes/detail/{class_id}', [ClassUserController::class, 'getClassDetail']);


        //Tạo môn học
        //IMPORT EXCEL 
        // API 2: Import dữ liệu từ folder
        Route::post('/import-exam-section', [ExcelController::class, 'importExamSection']);
        


        //Base Logic 

        //Tài khoản
        // Route::put('/accounts/{id}', [AdminController::class, 'update']); // Cập nhật hoặc xóa mềm tài khoản
        //Giáo viên
        // Route::get('/teachers/{id}', [TeacherController::class, 'show']); // Xem chi tiết giáo viên
        // Route::put('/teachers/{id}', [TeacherController::class, 'update']); // Chỉnh sửa hoặc xóa mềm giáo viên
        // Lớp học của giáo viên
        // Route::get('/class/{id}', [ClassController::class, 'show']); // Xem chi tiết lớp học
        // Route::put('/class/{id}', [ClassController::class, 'update']); // Sửa và xóa mềm lớp học
        //Học viên
        // Route::get('/students/{id}', [StudentController::class, 'show']); // Xem chi tiết học viên        // Route::put('/students/{id}', [StudentController::class, 'update']); // Chỉnh sửa hoặc xóa mềm học viên
        //Thêm học viên vào lớp
        // Route::post('/classes/students', [ClassUserController::class, 'addStudentToClass']);//Thêm học sinh vào lớp học
        //Xem lớp học của học viên
        // Route::get('/classes/students/{user_id}', [ClassUserController::class, 'getStudentClasses']);//Xem lớp học của học viên
        // Bằng cấp của giáo viên
        // Route::get('/diploma', [DiplomaController::class, 'index']);  // Xem danh sách tất cả bằng cấp hoặc của 1 giáo viên
        // Route::post('/diploma', [DiplomaController::class, 'store']); // Thêm bằng cấp
        // Route::put('/diploma/{id}', [DiplomaController::class, 'update']); // Sửa và xóa mềm bằng cấp
        // Route::get('/diploma/{id}', [DiplomaController::class, 'show']); // Xem chi tiết bằng cấp   
        //Lịch sử làm bài thi của học viên 
        // Route::get('/exam-results/history/{user_id}', [ExamResultController::class, 'examHistory']); //Xem danh sách lịch sử bài thi của học viên
        // Route::get('/exam-results/search', [ExamResultController::class, 'searchExamResults']); //Tìm kiếm bài thi học viên đã làm
        // Route::get('/exam-results/detail/{exam_id}', [ExamResultController::class, 'examDetail']); //Xem thông tin chi tiết bài thi
        //Route::get('/exam-results/analysis/{user_id}', [ExamResultController::class, 'analyzePerformance']); 
        // Bài luyện thi toeic 
        // Route::get('/exam-sections/detail/{exam_id}', [ExamSectionController::class, 'detail']); // Xem thông tin chi tiết
        // Route::post('/exam-sections', [ExamSectionController::class, 'store']); // Thêm bài Luyện thi
        // Route::put('/exam-sections/{exam_section_id}', [ExamSectionController::class, 'update']); // Chỉnh sửa và xóa mềm thông tin

        
    });
});