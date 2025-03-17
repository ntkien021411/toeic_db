<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Validator;
use App\Models\ExamSection;
use App\Models\Question;


class ExcelController extends Controller
{
    // public function importExcel(Request $request)
    // {
    //     // Kiểm tra xem file có tồn tại không
    //     if (!$request->hasFile('file')) {
    //         return response()->json(['error' => 'No file uploaded'], 400);
    //     }

    //     $file = $request->file('file');

    //     // Kiểm tra định dạng file (chỉ chấp nhận .xlsx)
    //     if ($file->getClientOriginalExtension() !== 'xlsx') {
    //         return response()->json(['error' => 'Invalid file format. Only .xlsx is allowed'], 400);
    //     }

    //     // Đọc file Excel
    //     $spreadsheet = IOFactory::load($file->getPathname());
    //     $worksheet = $spreadsheet->getActiveSheet();

    //     // Sử dụng iterator để đọc nhanh từng hàng
    //     $data = [];
    //     foreach ($worksheet->getRowIterator(2) as $rowIndex => $row) { // Bắt đầu từ hàng số 2
    //         $cellIterator = $row->getCellIterator();
    //         $cellIterator->setIterateOnlyExistingCells(false); // Lấy tất cả cell, kể cả cell trống

    //         $rowData = [];
    //         foreach ($cellIterator as $cell) {
    //             $rowData[] = $cell->getValue();
    //         }

    //         // Kiểm tra nếu hàng không đủ cột
    //         if (count($rowData) < 8) {
    //             continue;
    //         }

    //         $data[] = [
    //             'question_number' => $rowData[0],
    //             'question_text' => $rowData[1],
    //             'option_a' => $rowData[2],
    //             'option_b' => $rowData[3],
    //             'option_c' => $rowData[4],
    //             'option_d' => $rowData[5],
    //             'correct_answer' => $rowData[6],
    //             'explain' => $rowData[7],
    //         ];
    //     }

    //     return response()->json($data);
    // }

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

            $folderPath = $request->folder_path;

            // Kiểm tra folder có tồn tại không
            if (!is_dir($folderPath)) {
                return response()->json([
                    'message' => 'Không tìm thấy thư mục',
                    'code' => 404
                ], 404);
            }

            // Tìm file Excel đầu tiên
            $excelFile = null;
            $files = scandir($folderPath);
            foreach ($files as $file) {
                if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['xlsx', 'xls'])) {
                    $excelFile = $folderPath . '/' . $file;
                    break;
                }
            }

            if (!$excelFile) {
                return response()->json([
                    'message' => 'Không tìm thấy file Excel trong folder',
                    'code' => 404
                ], 404);
            }

            // Đọc file Excel
            $spreadsheet = IOFactory::load($excelFile);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Bỏ qua hàng header
            array_shift($rows);

            $result = [];
            $uploadedFiles = [];

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

            // Xử lý từng dòng trong Excel
            foreach ($rows as $index => $row) {
                // Lấy tên file từ Excel
                $audioFileName = $row[0] ?? null;
                $imageFileName = $row[1] ?? null;

                // Thử các định dạng file khác nhau
                $audioExtensions = ['.mp3', '.wav', '.m4a'];
                $imageExtensions = ['.jpg', '.jpeg', '.png'];
                
                $audioFile = null;
                $imageFile = null;

                // Tìm file audio với các định dạng khác nhau
                if ($audioFileName) {
                    foreach ($audioExtensions as $ext) {
                        $tempPath = $folderPath . '/' . $audioFileName . $ext;
                        if (file_exists($tempPath)) {
                            $audioFile = $audioFileName . $ext;
                            break;
                        }
                    }
                }

                // Tìm file image với các định dạng khác nhau
                if ($imageFileName) {
                    foreach ($imageExtensions as $ext) {
                        $tempPath = $folderPath . '/' . $imageFileName . $ext;
                        if (file_exists($tempPath)) {
                            $imageFile = $imageFileName . $ext;
                            break;
                        }
                    }
                }

                // Upload và lấy URL cho audio file
                if ($audioFile && !isset($uploadedFiles['audio'][$audioFile])) {
                    $audioPath = $folderPath . '/' . $audioFile;
                    
                    if (file_exists($audioPath)) {
                        try {
                            $uploadedFiles['audio'][$audioFile] = Cloudinary::upload($audioPath, [
                                'resource_type' => 'video',
                                'timeout' => 300
                            ])->getSecurePath();
                            
                            \Log::info('Uploaded audio URL: ' . $uploadedFiles['audio'][$audioFile]);
                        } catch (\Exception $e) {
                            \Log::error('Error uploading audio: ' . $e->getMessage());
                        }
                    }
                }

                // Upload và lấy URL cho image file
                if ($imageFile && !isset($uploadedFiles['image'][$imageFile])) {
                    $imagePath = $folderPath . '/' . $imageFile;
                    
                    if (file_exists($imagePath)) {
                        try {
                            $uploadedFiles['image'][$imageFile] = Cloudinary::upload($imagePath, [
                                'timeout' => 300
                            ])->getSecurePath();
                            
                            \Log::info('Uploaded image URL: ' . $uploadedFiles['image'][$imageFile]);
                        } catch (\Exception $e) {
                            \Log::error('Error uploading image: ' . $e->getMessage());
                        }
                    }
                }

                // Tạo question mới trong database
                Question::create([
                    'exam_section_id' => $examSection->id,
                    'question_number' => $index + 1,
                    'image_url' => $uploadedFiles['image'][$imageFile] ?? null,
                    'audio_url' => $uploadedFiles['audio'][$audioFile] ?? null,
                    'part_number' => $request->part_number,
                    'question_text' => $row[3] ?? null,
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
                    'audio_file' => $audioFile,
                    'image_file' => $imageFile,
                    'audio_url' => $uploadedFiles['audio'][$audioFile] ?? null,
                    'image_url' => $uploadedFiles['image'][$imageFile] ?? null,
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
