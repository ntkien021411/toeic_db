<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Validator;
use App\Models\ExamSection;
use App\Models\Question;

class ExcelController extends Controller
{
    public function importExamSection(Request $request)
    {
        try {
            // Tăng thời gian thực thi
            ini_set('max_execution_time', 300);

            // Validate đầu vào
            $validator = Validator::make($request->all(), [
                'folder_path' => 'required|string',
                'exam_code' => 'required|string|max:50',
                'exam_name' => 'nullable|string|max:255',
                'section_name' => 'required|in:Listening,Reading,Full',
                'part_number' => 'required|in:1,2,3,4,5,6,7,Full',
                'year' => 'nullable|integer|min:1',
                'duration' => 'nullable|integer|min:1',
                'max_score' => 'nullable|integer|min:1',
                'type' => 'nullable|string|max:255',
                'is_Free' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            // Chuyển đổi đường dẫn Windows nếu cần
            $folderPath = str_replace('\\', '/', $request->folder_path);
            
            // Kiểm tra thư mục tồn tại
            if (!is_dir($folderPath)) {
                return response()->json([
                    'message' => 'Không tìm thấy thư mục',
                    'details' => [
                        'requested_path' => $folderPath
                    ],
                    'code' => 404
                ], 404);
            }

            // Tìm file Excel trong thư mục
            $excelFiles = glob($folderPath . '/*.{xlsx,xls}', GLOB_BRACE);
            if (empty($excelFiles)) {
                return response()->json([
                    'message' => 'Không tìm thấy file Excel trong thư mục',
                    'details' => [
                        'folder_path' => $folderPath
                    ],
                    'code' => 404
                ], 404);
            }

            // Lấy file Excel đầu tiên
            $excelPath = $excelFiles[0];
            
            // Đọc file Excel
            $spreadsheet = IOFactory::load($excelPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Bỏ qua hàng header
            array_shift($rows);

            // Tạo exam section mới
            $examSection = ExamSection::create([
                'exam_code' => $request->exam_code,
                'exam_name' => $request->exam_name,
                'section_name' => $request->section_name,
                'part_number' => $request->part_number,
                'question_count' => count($rows),
                'year' => $request->year,
                'duration' => $request->duration,
                'max_score' => $request->max_score,
                'type' => $request->type,
                'is_Free' => $request->is_Free ?? false
            ]);

            $result = [];

            // Xử lý từng dòng trong Excel
            foreach ($rows as $index => $row) {
                // Lấy tên file từ Excel
                $audioFileName = $row[0] ?? null;
                $imageFileName = $row[1] ?? null;

                // Upload và lấy URL cho audio file
                if ($audioFileName) {
                    $audioExtensions = ['mp3', 'wav', 'm4a'];
                    foreach ($audioExtensions as $ext) {
                        $audioPath = $folderPath . '/' . $audioFileName . '.' . $ext;
                        if (file_exists($audioPath)) {
                            try {
                                $audioUrl = Cloudinary::upload($audioPath, [
                                    'resource_type' => 'video',
                                    'timeout' => 300
                                ])->getSecurePath();
                                
                                \Log::info('Uploaded audio URL: ' . $audioUrl);
                                break;
                            } catch (\Exception $e) {
                                \Log::error('Error uploading audio: ' . $e->getMessage());
                            }
                        }
                    }
                }

                // Upload và lấy URL cho image file
                if ($imageFileName) {
                    $imageExtensions = ['jpg', 'jpeg', 'png'];
                    foreach ($imageExtensions as $ext) {
                        $imagePath = $folderPath . '/' . $imageFileName . '.' . $ext;
                        if (file_exists($imagePath)) {
                            try {
                                $imageUrl = Cloudinary::upload($imagePath, [
                                    'timeout' => 300
                                ])->getSecurePath();
                                
                                \Log::info('Uploaded image URL: ' . $imageUrl);
                                break;
                            } catch (\Exception $e) {
                                \Log::error('Error uploading image: ' . $e->getMessage());
                            }
                        }
                    }
                }

                // Tạo question mới trong database
                Question::create([
                    'exam_section_id' => $examSection->id,
                    'question_number' => $index + 1,
                    'image_url' => $imageUrl ?? null,
                    'audio_url' => $audioUrl ?? null,
                    'part_number' => $request->part_number,
                    'question_text' => $row[4] ?? null,
                    'option_a' => $row[6] ?? null,
                    'option_b' => $row[7] ?? null,
                    'option_c' => $row[8] ?? null,
                    'option_d' => $row[9] ?? null,
                    'correct_answer' => $row[10] ?? null
                ]);

                // Thêm thông tin vào kết quả
                $result[] = [
                    'question_number' => $index + 1,
                    'part_number' => $request->part_number,
                    'audio_file' => $audioFileName,
                    'image_file' => $imageFileName,
                    'audio_url' => $audioUrl ?? null,
                    'image_url' => $imageUrl ?? null,
                    'question_text' => $row[4] ?? null,
                    'option_a' => $row[6] ?? null,
                    'option_b' => $row[7] ?? null,
                    'option_c' => $row[8] ?? null,
                    'option_d' => $row[9] ?? null,
                    'correct_answer' => $row[10] ?? null
                ];
            }

            return response()->json([
                'message' => 'Xử lý dữ liệu thành công',
                'code' => 200,
                'data' => [
                    'exam_section' => $examSection,
                    'questions' => $result
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error in importExamSection: ' . $e->getMessage());
            return response()->json([
                'message' => 'Có lỗi xảy ra trong quá trình xử lý',
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }
}
