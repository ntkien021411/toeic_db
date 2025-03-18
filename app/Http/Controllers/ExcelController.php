<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\ExamSection;
use App\Models\Question;
use ZipArchive;
use RarArchive;
use Illuminate\Support\Facades\File;

class ExcelController extends Controller
{
    /**
     * API: Upload files và import dữ liệu
     */
    public function importExamSection(Request $request)
    {
        try {
            // Tăng thời gian thực thi
            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '256M');

            // Validate đầu vào
            $validator = Validator::make($request->all(), [
                'files' => 'required|array',
                'files.*' => 'required|file|mimes:zip',
                'exam_code' => 'required|string|max:50',
                'exam_name' => 'nullable|string|max:255',
                'section_name' => 'required|in:Listening,Reading,Full',
                'part_number' => 'required|in:1,2,3,4,5,6,7,Full',
                'year' => 'nullable|integer|min:1',
                'duration' => 'nullable|integer|min:1',
                'max_score' => 'nullable|integer|min:1',
                'type' => 'nullable|string|max:255',
                'is_Free' => 'required|in:true,false,0,1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'code' => 400
                ], 400);
            }

            // Convert is_Free to boolean
            $isFree = filter_var($request->is_Free, FILTER_VALIDATE_BOOLEAN);

            // Tạo thư mục tạm thời
            $tempFolderName = uniqid('temp_');
            $tempPath = storage_path('app/temp/' . $tempFolderName);
            
            // Đảm bảo thư mục tạm tồn tại
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0755, true);
            }

            // Quét và phân loại các file
            $uploadedFiles = [
                'excel' => [],
                'audio' => [],
                'image' => []
            ];

            // Xử lý file upload
            foreach ($request->file('files') as $file) {
                try {
                    $fileName = $file->getClientOriginalName();
                    $extension = strtolower($file->getClientOriginalExtension());
                    
                    // Lưu file vào thư mục tạm
                    $file->move($tempPath, $fileName);
                    $archivePath = $tempPath . '/' . $fileName;

                    // Xử lý file zip
                    if ($extension === 'zip') {
                        $zip = new ZipArchive();
                        if ($zip->open($archivePath) === TRUE) {
                            // Giải nén vào thư mục tạm
                            $zip->extractTo($tempPath);
                            $zip->close();
                            
                            // Xóa file zip sau khi giải nén
                            @unlink($archivePath);

                            // Tìm tất cả file trong thư mục tạm và thư mục con
                            $allFiles = $this->getAllFiles($tempPath);

                            foreach ($allFiles as $extractedFile) {
                                $extractedFileName = basename($extractedFile);
                                $extractedExtension = strtolower(pathinfo($extractedFile, PATHINFO_EXTENSION));
                                
                                // Di chuyển file lên thư mục gốc nếu nó nằm trong thư mục con
                                if (dirname($extractedFile) !== $tempPath) {
                                    $newPath = $tempPath . '/' . $extractedFileName;
                                    rename($extractedFile, $newPath);
                                }
                                
                                // Phân loại file
                                if (in_array($extractedExtension, ['xlsx', 'xls'])) {
                                    $uploadedFiles['excel'][] = $extractedFileName;
                                } elseif (in_array($extractedExtension, ['mp3', 'wav', 'm4a'])) {
                                    $uploadedFiles['audio'][] = $extractedFileName;
                                } elseif (in_array($extractedExtension, ['jpg', 'jpeg', 'png'])) {
                                    $uploadedFiles['image'][] = $extractedFileName;
                                }
                            }
                        } else {
                            throw new \Exception('Không thể mở file zip');
                        }
                    } else {
                        throw new \Exception('Chỉ chấp nhận file ZIP');
                    }

                } catch (\Exception $e) {
                    // Xóa thư mục tạm nếu có lỗi
                    File::deleteDirectory($tempPath);
                    throw new \Exception('Lỗi xử lý file ' . $fileName . ': ' . $e->getMessage());
                }
            }

            // Kiểm tra file Excel
            if (empty($uploadedFiles['excel'])) {
                File::deleteDirectory($tempPath);
                return response()->json([
                    'message' => 'Không tìm thấy file Excel',
                    'code' => 404
                ], 404);
        }

        // Đọc file Excel
            $excelPath = $tempPath . '/' . $uploadedFiles['excel'][0];
            if (!File::exists($excelPath)) {
                File::deleteDirectory($tempPath);
                return response()->json([
                    'message' => 'Không tìm thấy file Excel',
                    'code' => 404
                ], 404);
            }

            try {
                $spreadsheet = IOFactory::load($excelPath);
        $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Bỏ qua hàng header
                array_shift($rows);

                // Upload tất cả file media lên Cloudinary trước
                $processedFiles = [
                    'audio' => [],
                    'image' => []
                ];

                // Lấy danh sách file cần upload từ Excel
                $mediaFiles = [
                    'audio' => [],
                    'image' => []
                ];
                
                foreach ($rows as $row) {
                    $audioFileName = trim($row[0] ?? '');
                    $imageFileName = trim($row[1] ?? '');
                    
                    if ($audioFileName) {
                        // Tìm file audio với các extension có thể
                        foreach (['mp3', 'wav', 'm4a'] as $ext) {
                            $fullFileName = $audioFileName . '.' . $ext;
                            $audioPath = $this->findFileInDirectory($tempPath, $fullFileName);
                            if ($audioPath) {
                                $mediaFiles['audio'][$audioFileName] = $audioPath;
                                break;
                            }
                        }
                    }
                    
                    if ($imageFileName) {
                        // Tìm file image với các extension có thể
                        foreach (['jpg', 'jpeg', 'png'] as $ext) {
                            $fullFileName = $imageFileName . '.' . $ext;
                            $imagePath = $this->findFileInDirectory($tempPath, $fullFileName);
                            if ($imagePath) {
                                $mediaFiles['image'][$imageFileName] = $imagePath;
                                break;
                            }
                        }
                    }
                }

                // Upload audio files
                foreach ($mediaFiles['audio'] as $fileName => $filePath) {
                    if (File::exists($filePath)) {
                        try {
                            $uploadResult = Cloudinary::upload($filePath, [
                                'resource_type' => 'video',
                                'timeout' => 300,
                                'folder' => 'toeic/audio'
                            ]);
                            $processedFiles['audio'][$fileName] = $uploadResult->getSecurePath();
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }

                // Upload image files
                foreach ($mediaFiles['image'] as $fileName => $filePath) {
                    if (File::exists($filePath)) {
                        try {
                            $uploadResult = Cloudinary::upload($filePath, [
                                'resource_type' => 'image',
                                'timeout' => 300,
                                'folder' => 'toeic/images'
                            ]);
                            $processedFiles['image'][$fileName] = $uploadResult->getSecurePath();
                        } catch (\Exception $e) {
                continue;
                        }
                    }
                }

                \Log::info('All processed files:', $processedFiles);

                if (empty($processedFiles['audio']) && empty($processedFiles['image'])) {
                    \Log::warning('No files were uploaded to Cloudinary');
                }

                // Tạo exam section sau khi đã upload xong tất cả file
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
                    'is_Free' => $isFree
                ]);

                $result = [];

                // Tạo questions với URL đã có sẵn
                foreach ($rows as $index => $row) {
                    $audioFileName = trim($row[0] ?? '');
                    $imageFileName = trim($row[1] ?? '');
                    
                    $audioUrl = null;
                    $imageUrl = null;

                    if ($audioFileName && isset($processedFiles['audio'][$audioFileName])) {
                        $audioUrl = $processedFiles['audio'][$audioFileName];
                    }

                    if ($imageFileName && isset($processedFiles['image'][$imageFileName])) {
                        $imageUrl = $processedFiles['image'][$imageFileName];
                    }

                    // Tạo question mới
                    $question = Question::create([
                        'exam_section_id' => $examSection->id,
                        'question_number' => $index + 1,
                        'image_url' => $imageUrl,
                        'audio_url' => $audioUrl,
                        'part_number' => $request->part_number,
                        'question_text' => $row[3] ?? null,
                        'explanation' => $row[9] ?? null,
                        'option_a' => $row[4] ?? null,
                        'option_b' => $row[5] ?? null,
                        'option_c' => $row[6] ?? null,
                        'option_d' => $row[7] ?? null,
                        'correct_answer' => $row[8] ?? null
                    ]);

                    // Thêm vào kết quả
                    $result[] = [
                        'exam_section_id' => $examSection->id,
                        'question_number' => $index + 1,
                        'part_number' => $request->part_number,
                        'audio_file' => $audioFileName,
                        'image_file' => $imageFileName,
                        'audio_url' => $audioUrl,
                        'image_url' => $imageUrl,
                        'question_text' => $row[3] ?? null,
                        'explanation' => $row[9] ?? null,
                        'option_a' => $row[4] ?? null,
                        'option_b' => $row[5] ?? null,
                        'option_c' => $row[6] ?? null,
                        'option_d' => $row[7] ?? null,
                        'correct_answer' => $row[8] ?? null
                    ];
                }

                // Xóa thư mục tạm
                File::deleteDirectory($tempPath);

                return response()->json([
                    'message' => 'Xử lý dữ liệu thành công',
                    'code' => 200,
                    'data' => [
                        'exam_section' => $examSection,
                        'questions' => $result
                    ]
                ], 200);

            } catch (\Exception $e) {
                // Xóa thư mục tạm nếu có lỗi
                File::deleteDirectory($tempPath);
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Có lỗi xảy ra trong quá trình xử lý',
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    /**
     * Lấy tất cả file trong một thư mục và các thư mục con
     */
    private function getAllFiles($dir) {
        $files = [];
        
        // Quét tất cả file và thư mục
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            
            if (is_dir($path)) {
                // Nếu là thư mục, đệ quy để lấy file trong thư mục con
                $files = array_merge($files, $this->getAllFiles($path));
            } else {
                // Nếu là file, thêm vào danh sách
                $files[] = $path;
            }
        }
        
        return $files;
    }

    private function findFileInDirectory($dir, $fileName) {
        $files = $this->getAllFiles($dir);
        foreach ($files as $file) {
            if (basename($file) === $fileName) {
                return $file;
            }
        }
        return null;
    }
}
