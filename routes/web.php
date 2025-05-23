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
        // Route::post('/upload', function (Request $request) {
        //     return response()->json($request->uploaded_urls);
        // })->middleware('upload.image');


        //EXAMSECTION Bài luyện thi toeic 
        Route::get('/tests-full/list', [ExamSectionController::class, 'listExam']); // Xem danh sách bài Luyện thi full 

        //Xem danh sách tất cả bài thi toeic
        Route::get('/exam-sections/all', [ExamSectionController::class, 'getAllExamSections']);  // API mới
          //Xem danh sách câu hỏi của bài thi toeic theo từng bài 
        Route::get('/exam-sections/{examCode}/questions', [ExamSectionController::class, 'getQuestionsByExamCode']);  //
 
         //Xem danh sách bài thi toeic theo từng part và exam_code 
         Route::get('/list-exam-section/{exam_code}', [ExamSectionController::class, 'checkExamParts']);
 
         //Xem danh sách câu hỏi của bài thi toeic theo từng bài và từng part 
         Route::get('/exam-sections/{exam_code}/{part_number}/questions', [ExamSectionController::class, 'getQuestionsByExamSection']);

         //Xem danh sách câu hỏi của bài thi toeic full 
         Route::get('/exam-sections-full/{exam_code}/questions', [ExamSectionController::class, 'getQuestionsByExamSectionFull']);
         //Tính điểm bài thi toeic 
         Route::post('/submit-exam', [ExamResultController::class, 'submitExam']);
         //Xem thống kê bài thi toeic 
         Route::get('/exam-results/statistics', [ExamResultController::class, 'getStatistics']);
        //Upload ảnh base 64 
        Route::post('/upload-base64', [ExcelController::class, 'uploadBase64Files']);
        //Sửa  thông tin học sinh 
        Route::put('/students/edit-student/{id}', [StudentController::class, 'editUser']); // Sửa học viên
        Route::put('/students/edit-image/{id}', [StudentController::class, 'editImage']); 

         //Xem lịch sử thi 
         Route::get('/exam-history/{userId}', [ExamResultController::class, 'getUserExamHistory']);
         //Xem chi tiết của bài thi lấy từ lịch sử thi 
         Route::post('/exam-history-detail', [ExamResultController::class, 'getExamDetails']);

    });

   
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        // Route::post('/refresh', [AuthController::class, 'refresh']);
        // Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/', [AuthController::class, 'checkAccount']);
        Route::get('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    //Quyền giáo viên và admin 
    Route::middleware(['checkAdminTeacher'])->group(function () {

        Route::get('/teachers/list', [TeacherController::class, 'listTeacher']); 
        Route::put('/teachers/edit-teacher-own/{id}', [TeacherController::class, 'editUser']); // Sửa giáo viên
        Route::put('/teachers/edit-teacher-image/{id}', [TeacherController::class, 'editTeacher']); 
        //Danh sách lớp học 
        Route::get('/classes/list', [ClassController::class, 'listClass']); 
        //Chi tiết lớp học
        Route::get('/classes/detail/{class_id}', [ClassUserController::class, 'getClassDetail']);

        Route::get('/students/list', [StudentController::class, 'listStudent']);


        Route::get('/diploma/list/{user_id}', [DiplomaController::class, 'index']);  // Xem danh sách tất cả bằng cấp hoặc của 1 giáo viên
        
        // DIPLOMA
        Route::post('/diploma/add-diploma-teacher', [DiplomaController::class, 'store']); // Thêm bằng cấp
        Route::put('/diploma/edit-diploma/{id}', [DiplomaController::class, 'updateAdmin']); // Sửa  bằng cấp
        Route::delete('/diploma/delete', [DiplomaController::class, 'softDelete']); // Xem chi tiết bằng cấp   

    });

    // ✅ Bảo vệ API bằng middleware 
    //Chỉ Admin dùng được
    Route::middleware(['checkAdmin'])->group(function () {

        Route::put('/teachers/edit-teacher/{id}', [TeacherController::class, 'edit']); // Sửa giáo viên

        Route::put('/teachers/edit-image/{id}', [TeacherController::class, 'editTeacherAdmin']); 

        //TEACHER Xem danh sách giáo viên 
        Route::post('/teachers/add-teacher', [TeacherController::class, 'createUser']); // Thêm giáo viên

        Route::delete('/teachers/delete', [TeacherController::class, 'delete']); 

        //STUDENT Xem danh sách học viên
        Route::post('/students/add-student', [StudentController::class, 'createUser']); // Thêm học viên
        Route::delete('/students/delete', [StudentController::class, 'delete']); 
       
        //ACCOUNT Xem danh sách tài khoản
        Route::get('/users/list', [AdminController::class, 'listAccount']);
        Route::patch('/users/update-status-user', [AdminController::class, 'updateStatus']);
        Route::delete('/users/delete', [AdminController::class, 'delete']); 

        //CLASS Thêm lớp học 
        Route::post('/class', [ClassController::class, 'store']);  // Tạo lớp học
        Route::put('/class/edit-class/{id}', [ClassController::class, 'edit']);  // Sửa lớp học
        Route::delete('/classes/delete', [ClassController::class, 'delete']);  // Xóa lớp học
        //Tạo môn học
        //IMPORT EXCEL 
        // API 2: Import dữ liệu từ folder

        //Tạo bài thi toeic 
        Route::post('/create-exam-section', [ExamSectionController::class, 'createExamSection']);
        // //Xem danh sách bài thi toeic theo từng part và exam_code 
        // Route::get('/list-exam-section/{exam_code}', [ExamSectionController::class, 'checkExamParts']);
        //Import câu hỏi bài thi toeic từ file excel    
        Route::post('/read-excel/{part_number}', [ExcelController::class, 'readExcel']);
        //Tạo câu hỏi cho bài thi toeic
        Route::post('/create-question/{exam_code}/{part_number}', [ExcelController::class, 'importQuestions']);
        //Sửa bài thi 
        Route::put('/update-exam-section/{id}', [ExamSectionController::class, 'editExamSection']);

        //Thêm học viên vào lớp
        Route::post('/classes/students', [ClassUserController::class, 'addStudentToClass']);//Thêm học sinh vào lớp học

        //Xóa bài thi 
        Route::delete('/exams/delete', [ExamSectionController::class, 'deleteExamSections']);


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

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
