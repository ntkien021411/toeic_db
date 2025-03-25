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
use Illuminate\Support\Facades\DB;

class ExcelController extends Controller
{
    /**
     * Đọc file Excel và trả về dữ liệu dạng JSON
     * File Excel cần có cấu trúc:
     * - Cột 1: Số thứ tự câu hỏi
     * - Cột 2: Câu hỏi
     * - Cột 3-6: Đáp án A,B,C,D
     * - Cột 7: Đáp án đúng
     * - Cột 8: Giải thích (định dạng: 4 dòng tiếng Anh + 4 dòng tiếng Việt + từ vựng)
     */
    public function readExcel(Request $request, $part_number)
    {
        try {
            // Validate part_number
            $validator = \Validator::make(['part_number' => $part_number], [
                'part_number' => 'required|integer|min:1|max:7'
            ], [
                'part_number.required' => 'Vui lòng chọn phần thi.',
                'part_number.integer' => 'Phần thi phải là số nguyên.',
                'part_number.min' => 'Phần thi phải từ 1 đến 7.',
                'part_number.max' => 'Phần thi phải từ 1 đến 7.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu đầu vào không hợp lệ.',
                    'code' => 400,
                    'data' => null,
                    'meta' => null,
                    'message_array' => $validator->errors()
                ], 400);
            }

            // Kiểm tra file upload
            $validator = \Validator::make($request->all(), [
                'file' => [
                    'required',
                    'file',
                    'mimes:xlsx,xls',
                    'max:2048',
                    function ($attribute, $value, $fail) {
                        $mimeType = $value->getMimeType();
                        $allowedMimes = [
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/octet-stream',
                            'application/zip'
                        ];
                        
                        if (!in_array($mimeType, $allowedMimes)) {
                            $fail('File phải là định dạng Excel (.xlsx hoặc .xls)');
                        }
                    }
                ]
            ], [
                'file.required' => 'Vui lòng chọn file Excel để tải lên.',
                'file.mimes' => 'File phải có định dạng xlsx hoặc xls.',
                'file.max' => 'Kích thước file không được vượt quá 2MB.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu đầu vào không hợp lệ.',
                    'code' => 400,
                    'data' => null,
                    'meta' => null,
                    'message_array' => $validator->errors()
                ], 400);
        }

        // Đọc file Excel
            $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Bỏ qua dòng tiêu đề
            array_shift($rows);

            $questions = [];
            foreach ($rows as $row) {
                // Bỏ qua dòng trống
                if (empty($row[0])) continue;

                // Xử lý phần giải thích
                $explanationText = $row[7] ?? '';
                $formattedExplanation = '';
                
                // Tách thành từng dòng
                $lines = explode("\n", $explanationText);
                
                // Lọc bỏ dòng trống và thêm vào formattedExplanation
                foreach ($lines as $line) {
                    if (!empty(trim($line))) {
                        $formattedExplanation .= $line . "\n";
                    }
                }

                // Tạo cấu trúc dữ liệu cho mỗi câu hỏi
                $question = (object)[
                    'question_number' => intval($row[0]),
                    'part_number' => $part_number,
                    'question_text' => $row[1] ?? '',
                    'option_a' => $row[2] ?? '',
                    'option_b' => $row[3] ?? '',
                    'option_c' => $row[4] ?? '',
                    'option_d' => $row[5] ?? '',
                    'correct_answer' => $row[6] ?? '',
                    'explanation' => $formattedExplanation,
                    'audio_url' => $row[9] ?? '',
                    'image_url' => $row[10] ?? ''
                ];

                // Tạo cấu trúc dữ liệu cho mỗi câu hỏi
                $questions[] = $question;
            }

            // Nhóm câu hỏi theo part_number
            $groupedQuestions = [];
            
            // Sắp xếp câu hỏi theo số thứ tự
            usort($questions, function($a, $b) {
                return $a->question_number - $b->question_number;
            });

            // Xử lý nhóm theo part_number
            switch ($part_number) {
                case 1:
                case 2:
                case 5:
                    // Part 1, 2, 5: Mỗi group có một object question
                    $totalQuestions = count($questions);
                    for ($i = 0; $i < $totalQuestions; $i++) {
                        $question = $questions[$i];
                        $groupedQuestions[] = [
                            'questions' => $question,
                            // 'audio_url' => $question->audio_url,
                            // 'image_url' => $question->image_url
                        ];
                    }
                    break;

                case 3:
                case 4:
                case 6:
                case 7:
                    // Part 3,4,6,7: Mỗi group có một mảng questions
                    $totalQuestions = count($questions);
                    $groupSize = 3; // Default size for part 3,4

                    if ($part_number == 6) {
                        $groupSize = 4;
                    } else if ($part_number == 7) {
                        // Pattern đặc biệt cho part 7
                        $pattern = [2, 2, 2, 3, 3, 3, 4, 4, 4, 4, 4, 4, 5, 5, 5];
                        $currentIndex = 0;
                        $patternIndex = 0;
                        
                        while ($currentIndex < $totalQuestions) {
                            $groupSize = $pattern[$patternIndex % count($pattern)];
                            $group = array_slice($questions, $currentIndex, $groupSize);
                            $audioUrl = $group[0]->audio_url ?? null;
                            $imageUrl = $group[0]->image_url ?? null;
                            
                            $groupQuestions = [];
                            foreach ($group as $question) {
                                unset($question->audio_url);
                                unset($question->image_url);
                                $groupQuestions[] = $question;
                            }
                            
                            $groupedQuestions[] = [
                                'questions' => $groupQuestions,
                                'audio_url' => $audioUrl,
                                'image_url' => $imageUrl
                            ];
                            
                            $currentIndex += $groupSize;
                            $patternIndex++;
                        }
                        break;
                    }

                    // Xử lý cho part 3,4,6
                    for ($i = 0; $i < $totalQuestions; $i += $groupSize) {
                        $group = array_slice($questions, $i, $groupSize);
                        $audioUrl = $group[0]->audio_url ?? null;
                        $imageUrl = $group[0]->image_url ?? null;
                        
                        $groupQuestions = [];
                        foreach ($group as $question) {
                            unset($question->audio_url);
                            unset($question->image_url);
                            $groupQuestions[] = $question;
                        }
                        
                        $groupedQuestions[] = [
                            'questions' => $groupQuestions,
                            'audio_url' => $audioUrl,
                            'image_url' => $imageUrl
                        ];
                    }
                    break;
            }

            // Trả về kết quả
            return response()->json([
                'message' => 'Đọc file Excel thành công.',
                'code' => 200,
                'data' => [
                    'part_number' => $part_number,
                    'groups' => $groupedQuestions
                ],
                'meta' => [
                    'total_questions' => count($questions),
                    'total_groups' => count($groupedQuestions)
                ]
            ], 200);

        } catch (\Exception $e) {
            // Xử lý lỗi
            return response()->json([
                'message' => 'Lỗi khi đọc file Excel.',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    //CLOSING 
    // private function uploadToCloudinary($url, $type = 'image')
    // {
    //     try {
    //         // Nếu URL rỗng hoặc null, trả về chuỗi rỗng
    //         if (empty($url) || $url === null) {
    //             return '';
    //         }

    //         // Nếu URL đã là Cloudinary URL, trả về chuỗi rỗng
    //         if (strpos($url, 'res.cloudinary.com') !== false) {
    //             return '';
    //         }

    //         // Thêm header cho base64 nếu chưa có
    //         if (base64_decode($url, true)) {
    //             // Xác định mime type dựa vào type
    //             $mimeType = $type === 'image' ? 'image/png' : 'audio/mpeg';
    //             // Thêm header nếu chuỗi chưa có
    //             if (strpos($url, 'data:') !== 0) {
    //                 $url = 'data:' . $mimeType . ';base64,' . $url;
    //             }
    //         }

    //         // Kiểm tra và upload nếu là base64
    //         if (strpos($url, 'data:') === 0) {
    //             // Upload lên Cloudinary
    //             $result = Cloudinary::upload($url, [
    //                 'resource_type' => $type === 'image' ? 'image' : 'video',
    //                 'folder' => $type === 'image' ? 'images' : 'audio'
    //             ]);

    //             // Lấy secure URL từ kết quả upload
    //             return $result->getSecurePath();
    //         }

    //         return '';

    //     } catch (\Exception $e) {
    //         \Log::error('Cloudinary upload error: ' . $e->getMessage());
    //         return '';
    //     }
    // }

    public function importQuestions(Request $request, $exam_code, $part_number)
    {
        try {
            DB::beginTransaction();
            
            // Validate exam section exists
            $examSection = ExamSection::where('exam_code', $exam_code)
                ->where('part_number', $part_number)
                ->where('is_deleted', false)
                ->first();

            if (!$examSection) {
                return response()->json([
                    'message' => 'Không tìm thấy phần thi.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Kiểm tra xem đã có câu hỏi cho phần thi này chưa
            $existingQuestions = Question::where('exam_section_id', $examSection->id)->count();
            if ($existingQuestions > 0) {
                return response()->json([
                    'message' => 'Phần thi này đã có câu hỏi.',
                    'code' => 400,
                    'data' => null
                ], 400);
            }

            // Get data from request
            $data = $request->all();
            
            // Kiểm tra cấu trúc data
            if (!isset($data['data']) || !isset($data['data']['groups']) || !is_array($data['data']['groups'])) {
                return response()->json([
                    'message' => 'Dữ liệu câu hỏi không đúng định dạng. Cần có trường data.groups là một mảng.',
                    'code' => 400,
                    'data' => null
                ], 400);
            }

            // Tính tổng số câu hỏi từ data
            $totalQuestions = 0;
            foreach ($data['data']['groups'] as $group) {
                if (isset($group['questions'])) {
                    if (in_array($part_number, [1, 2, 5])) {
                        // Part 1, 2, 5: questions là một object
                        $totalQuestions++;
                    } else {
                        // Part 3,4,6,7: questions là mảng chứa nhiều object
                        $totalQuestions += count($group['questions']);
                    }
                }
            }

            // Kiểm tra số lượng câu hỏi
            if ($totalQuestions !== $examSection->question_count) {
                return response()->json([
                    'message' => 'Số lượng câu hỏi không khớp với yêu cầu của phần thi. Bạn đã gửi ' . $totalQuestions . ' câu hỏi, trong khi phần thi yêu cầu ' . $examSection->question_count . ' câu hỏi.',
                    'code' => 400,
                    'data' => [
                        'required' => $examSection->question_count,
                        'received' => $totalQuestions,
                        'difference' => $examSection->question_count - $totalQuestions
                    ]
                ], 400);
            }

            $questionsToInsert = [];

            foreach ($data['data']['groups'] as $group) {
                if (isset($group['questions'])) {
                    if (in_array($part_number, [1, 2, 5])) {
                        // Part 1, 2, 5: questions là một object
                        $questionData = $group['questions'];
                        $questionsToInsert[] = [
                            'exam_section_id' => $examSection->id,
                            'question_number' => $questionData['question_number'] ?? null,
                            'part_number' => $part_number,
                            'question_text' => $questionData['question_text'] ?? null,
                            'option_a' => $questionData['option_a'] ?? null,
                            'option_b' => $questionData['option_b'] ?? null,
                            'option_c' => $questionData['option_c'] ?? null,
                            'option_d' => $questionData['option_d'] ?? null,
                            'correct_answer' => isset($questionData['correct_answer']) ? strtoupper($questionData['correct_answer']) : null,
                            'explanation' => $questionData['explanation'] ?? null,
                            'audio_url' => $questionData['audio_url'] ?? null,
                            'image_url' => $questionData['image_url'] ?? null
                        ];
                    } else {
                        // Part 3,4,6,7: questions là mảng chứa nhiều object
                        foreach ($group['questions'] as $questionData) {
                            $questionsToInsert[] = [
                                'exam_section_id' => $examSection->id,
                                'question_number' => $questionData['question_number'] ?? null,
                                'part_number' => $examSection->part_number,
                                'question_text' => $questionData['question_text'] ?? null,
                                'option_a' => $questionData['option_a'] ?? null,
                                'option_b' => $questionData['option_b'] ?? null,
                                'option_c' => $questionData['option_c'] ?? null,
                                'option_d' => $questionData['option_d'] ?? null,
                                'correct_answer' => isset($questionData['correct_answer']) ? strtoupper($questionData['correct_answer']) : null,
                                'explanation' => $questionData['explanation'] ?? null,
                                'audio_url' => $group['audio_url'] ?? null,
                                'image_url' => $group['image_url'] ?? null
                            ];
                        }
                    }
                }
            }

            // Insert tất cả câu hỏi một lần
            Question::insert($questionsToInsert);

            // Lấy lại danh sách câu hỏi đã insert để trả về
            $questions = Question::where('exam_section_id', $examSection->id)
                ->orderBy('question_number')
                ->get()
                ->map(function($question) {
                    return [
                        'id' => $question->id,
                        'exam_section_id' => $question->exam_section_id,
                        'question_number' => $question->question_number,
                        'part_number' => $question->part_number,
                        'question_text' => $question->question_text,
                        'option_a' => $question->option_a,
                        'option_b' => $question->option_b,
                        'option_c' => $question->option_c,
                        'option_d' => $question->option_d,
                        'correct_answer' => $question->correct_answer,
                        'explanation' => $question->explanation,
                        'audio_url' => $question->audio_url,
                        'image_url' => $question->image_url
                    ];
                });

            DB::commit();

            return response()->json([
                'message' => 'Thêm câu hỏi thành công.',
                'code' => 201,
                'data' => [
                    'exam_section_id' => $examSection->id,
                    'questions_count' => count($questions),
                    'questions' => $questions
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi thêm câu hỏi: ' . $e->getMessage(),
                'code' => 500,
                'data' => null
            ], 500);
        }
    }   

    private function isValidBase64($base64)
    {
        // Kiểm tra nếu thiếu "data:image/...;base64," hoặc "data:audio/...;base64,"
        if (!preg_match('/^data:(image\/(png|jpeg|gif|jpg)|audio\/(mpeg|wav|ogg));base64,/', $base64)) {
            return false;
        }

        // Lấy phần dữ liệu thực tế
        $data = substr($base64, strpos($base64, ',') + 1);

        // Kiểm tra xem phần dữ liệu có thực sự là Base64 không
        return base64_decode($data, true) !== false;
    }

    public function uploadBase64Files(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'audio_base64' => 'nullable|string',
                'image_base64' => 'nullable|string'
            ], [
                'audio_base64.string' => 'Audio base64 phải là chuỗi.',
                'image_base64.string' => 'Image base64 phải là chuỗi.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu đầu vào không hợp lệ.',
                    'code' => 400,
                    'data' => null,
                    'errors' => $validator->errors()
                ], 400);
            }

            $response = [
                'audio_url' => '',
                'image_url' => ''
            ];

            // Handle audio upload
            if ($request->has('audio_base64') && !empty($request->audio_base64)) {
                $audioBase64 = $request->audio_base64;
                
                // Add data URI if not present
                if (strpos($audioBase64, 'data:') !== 0) {
                    $audioBase64 = 'data:audio/mpeg;base64,' . $audioBase64;
                }

                if ($this->isValidBase64($audioBase64)) {
                    $result = Cloudinary::upload($audioBase64, [
                        'resource_type' => 'video',
                        'folder' => 'audio'
                    ]);
                    $response['audio_url'] = $result->getSecurePath();
                }
            }

            // Handle image upload
            if ($request->has('image_base64') && !empty($request->image_base64)) {
                $imageBase64 = $request->image_base64;
                
                // Add data URI if not present
                if (strpos($imageBase64, 'data:') !== 0) {
                    $imageBase64 = 'data:image/png;base64,' . $imageBase64;
                }

                if ($this->isValidBase64($imageBase64)) {
                    $result = Cloudinary::upload($imageBase64, [
                        'resource_type' => 'image',
                        'folder' => 'images'
                    ]);
                    $response['image_url'] = $result->getSecurePath();
                }
            }

            return response()->json([
                'message' => 'Upload files thành công.',
                'code' => 200,
                'data' => $response
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi upload files: ' . $e->getMessage(),
                'code' => 500,
                'data' => null
            ], 500);
        }
    }
}
