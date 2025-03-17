<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\ExamSection;
use App\Models\Question;

class ExcelController extends Controller
{
    /**
     * API 1: Copy folder vào storage của server
     */
    public function copyFolder(Request $request)
    {
        try {
            // Validate đầu vào
            $validator = Validator::make($request->all(), [
                'folder_path' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            // Chuẩn hóa đường dẫn
            $sourcePath = str_replace('\\', '/', $request->folder_path);
            $sourcePath = rtrim($sourcePath, '/');

            // Kiểm tra thư mục nguồn tồn tại
            if (!is_dir($sourcePath)) {
                return response()->json([
                    'message' => 'Không tìm thấy thư mục nguồn',
                    'details' => [
                        'folder_path' => $sourcePath
                    ],
                    'code' => 404
                ], 404);
            }

            // Sử dụng thư mục cố định là 'sample'
            $storagePath = 'uploads/sample';
            
            // Xóa thư mục cũ nếu tồn tại
            if (Storage::exists($storagePath)) {
                Storage::deleteDirectory($storagePath);
            }
            
            // Tạo thư mục mới
            Storage::makeDirectory($storagePath);

            // Quét và phân loại các file
            $uploadedFiles = [
                'excel' => [],
                'audio' => [],
                'image' => []
            ];

            // Đọc tất cả file trong thư mục nguồn
            $files = scandir($sourcePath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                $filePath = $sourcePath . '/' . $file;
                if (!is_file($filePath)) continue;

                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                
                // Copy file vào storage
                Storage::put(
                    $storagePath . '/' . $file, 
                    file_get_contents($filePath)
                );

                // Phân loại file
                if (in_array($extension, ['xlsx', 'xls'])) {
                    $uploadedFiles['excel'][] = $file;
                } elseif (in_array($extension, ['mp3', 'wav', 'm4a'])) {
                    $uploadedFiles['audio'][] = $file;
                } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                    $uploadedFiles['image'][] = $file;
                }
            }

            if (empty($uploadedFiles['excel'])) {
                return response()->json([
                    'message' => 'Không tìm thấy file Excel trong thư mục',
                    'code' => 404
                ], 404);
            }

            return response()->json([
                'message' => 'Copy thư mục thành công',
                'code' => 200,
                'data' => [
                    'folder_name' => 'sample',
                    'excel_files' => $uploadedFiles['excel'],
                    'audio_files' => $uploadedFiles['audio'],
                    'image_files' => $uploadedFiles['image']
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error in copyFolder: ' . $e->getMessage());
            return response()->json([
                'message' => 'Có lỗi xảy ra trong quá trình copy',
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * API 2: Xử lý dữ liệu từ folder đã copy
     */
    public function importExamSection(Request $request)
    {
        try {
            // Tăng thời gian thực thi
            ini_set('max_execution_time', 300);

            // Validate đầu vào
            $validator = Validator::make($request->all(), [
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

            // Sử dụng thư mục cố định là 'sample'
            $folderPath = 'uploads/sample';
            if (!Storage::exists($folderPath)) {
                return response()->json([
                    'message' => 'Không tìm thấy thư mục sample',
                    'code' => 404
                ], 404);
            }

            // Tìm file Excel
            $excelFiles = Storage::files($folderPath);
            $excelFile = null;
            foreach ($excelFiles as $file) {
                if (str_ends_with(strtolower($file), '.xlsx') || str_ends_with(strtolower($file), '.xls')) {
                    $excelFile = $file;
                    break;
                }
            }

            if (!$excelFile) {
                return response()->json([
                    'message' => 'Không tìm thấy file Excel trong thư mục',
                    'code' => 404
                ], 404);
            }

            // Đọc file Excel
            $excelPath = Storage::path($excelFile);
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
            $processedFiles = [
                'audio' => [],
                'image' => []
            ];

            // Xử lý từng dòng trong Excel
            foreach ($rows as $index => $row) {
                $audioFileName = trim($row[0] ?? '');
                $imageFileName = trim($row[1] ?? '');
                $audioUrl = null;
                $imageUrl = null;

                // Upload audio file nếu chưa được xử lý
                if ($audioFileName && !isset($processedFiles['audio'][$audioFileName])) {
                    $audioPath = Storage::path($folderPath . '/' . $audioFileName);
                    if (file_exists($audioPath)) {
                        try {
                            $audioUrl = Cloudinary::upload($audioPath, [
                                'resource_type' => 'video',
                                'timeout' => 300
                            ])->getSecurePath();
                            $processedFiles['audio'][$audioFileName] = $audioUrl;
                            \Log::info('Uploaded audio: ' . $audioFileName . ' -> ' . $audioUrl);
                        } catch (\Exception $e) {
                            \Log::error('Error uploading audio ' . $audioFileName . ': ' . $e->getMessage());
                        }
                    } else {
                        \Log::warning('Audio file not found: ' . $audioFileName);
                    }
                } else if (isset($processedFiles['audio'][$audioFileName])) {
                    $audioUrl = $processedFiles['audio'][$audioFileName];
                }

                // Upload image file nếu chưa được xử lý
                if ($imageFileName && !isset($processedFiles['image'][$imageFileName])) {
                    $imagePath = Storage::path($folderPath . '/' . $imageFileName);
                    if (file_exists($imagePath)) {
                        try {
                            $imageUrl = Cloudinary::upload($imagePath, [
                                'timeout' => 300
                            ])->getSecurePath();
                            $processedFiles['image'][$imageFileName] = $imageUrl;
                            \Log::info('Uploaded image: ' . $imageFileName . ' -> ' . $imageUrl);
                        } catch (\Exception $e) {
                            \Log::error('Error uploading image ' . $imageFileName . ': ' . $e->getMessage());
                        }
                    } else {
                        \Log::warning('Image file not found: ' . $imageFileName);
                    }
                } else if (isset($processedFiles['image'][$imageFileName])) {
                    $imageUrl = $processedFiles['image'][$imageFileName];
                }

                // Tạo question mới trong database
                Question::create([
                    'exam_section_id' => $examSection->id,
                    'question_number' => $index + 1,
                    'image_url' => $imageUrl,
                    'audio_url' => $audioUrl,
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
                    'audio_url' => $audioUrl,
                    'image_url' => $imageUrl,
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
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Có lỗi xảy ra trong quá trình xử lý',
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }
}
