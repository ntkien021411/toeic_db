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
    public function readExcel(Request $request)
    {
        try {
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
                $explanations = explode("\n\n", $row[7] ?? '');
                $formattedExplanation = '';

                if (count($explanations) >= 2) {
                    // Xử lý phần tiếng Anh
                    $englishParts = array_filter(array_slice($explanations, 0, 4), function($part) {
                        return strpos($part, '(A)') === 0 || 
                               strpos($part, '(B)') === 0 || 
                               strpos($part, '(C)') === 0 || 
                               strpos($part, '(D)') === 0;
                    });

                    // Xử lý phần tiếng Việt
                    $vietnameseParts = array_filter(array_slice($explanations, 4), function($part) {
                        return strpos($part, '(A)') === 0 || 
                               strpos($part, '(B)') === 0 || 
                               strpos($part, '(C)') === 0 || 
                               strpos($part, '(D)') === 0;
                    });

                    // Gộp các phần lại với nhau và thêm định dạng
                    $englishText = implode("\n", $englishParts);
                    $vietnameseText = implode("\n", $vietnameseParts);
                    $vocabulary = array_slice($explanations, -1)[0] ?? '';

                    // Tìm và đánh dấu đáp án đúng bằng cách in đậm
                    $correctAnswer = $row[6] ?? '';
                    if ($correctAnswer) {
                        $englishText = preg_replace(
                            '/\(' . $correctAnswer . '\)(.*?)(\n|$)/', 
                            '**($correctAnswer)$1**$2', 
                            $englishText
                        );
                        $vietnameseText = preg_replace(
                            '/\(' . $correctAnswer . '\)(.*?)(\n|$)/', 
                            '**($correctAnswer)$1**$2', 
                            $vietnameseText
                        );
                    }

                    // Gộp tất cả thành một đoạn text
                    $formattedExplanation = $englishText . "\n\n" . $vietnameseText;
                    if (!empty($vocabulary)) {
                        $formattedExplanation .= "\n\n" . $vocabulary;
                    }
                }

                // Tạo cấu trúc dữ liệu cho mỗi câu hỏi
                $questions[] = [
                    'question_number' => $row[0] ?? '',
                    'question' => $row[1] ?? '',
                    'answer_a' => $row[2] ?? '',
                    'answer_b' => $row[3] ?? '',
                    'answer_c' => $row[4] ?? '',
                    'answer_d' => $row[5] ?? '',
                    'correct_answer' => $row[6] ?? '',
                    'explanation' => $formattedExplanation,
                    'audio' => "",
                    'image' => ""
                ];
            }

            // Trả về kết quả
            return response()->json([
                'message' => 'Đọc file Excel thành công.',
                'code' => 200,
                'data' => $questions,
                'meta' => [
                    'total_questions' => count($questions)
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

    public function importQuestions(Request $request, $exam_id)
    {
        try {
            // Validate exam section exists
            $examSection = ExamSection::where('id', $exam_id)
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
            $existingQuestions = Question::where('exam_section_id', $exam_id)->count();
            if ($existingQuestions > 0) {
                return response()->json([
                    'message' => 'Phần thi này đã có câu hỏi.',
                    'code' => 400,
                    'data' => null
                ], 400);
            }

            // Get questions data
            $questions = $request->input('questions');
            
            // Nếu questions là string, thử decode JSON
            if (is_string($questions)) {
                $questions = json_decode($questions, true);
            }
            
            // Kiểm tra xem questions có phải array không
            if (!is_array($questions)) {
                return response()->json([
                    'message' => 'Dữ liệu câu hỏi phải là một mảng.',
                    'code' => 400,
                    'data' => null
                ], 400);
            }
            
            // Validate questions count
            if (count($questions) !== $examSection->question_count) {
                return response()->json([
                    'message' => 'Số lượng câu hỏi không khớp với yêu cầu của phần thi. Bạn đã gửi ' . count($questions) . ' câu hỏi, trong khi phần thi yêu cầu ' . $examSection->question_count . ' câu hỏi.',
                    'code' => 400,
                    'data' => [
                        'required' => $examSection->question_count,
                        'received' => count($questions),
                        'difference' => $examSection->question_count - count($questions)
                    ]
                ], 400);
            }

            // Validate required fields for each question
            foreach ($questions as $index => $question) {
                if (!isset($question['question_number']) || 
                    !isset($question['question']) || 
                    !isset($question['answer_a']) || 
                    !isset($question['answer_b']) || 
                    !isset($question['answer_c']) || 
                    !isset($question['answer_d']) || 
                    !isset($question['correct_answer']) ||
                    !isset($question['explanation'])) {
                    return response()->json([
                        'message' => 'Thiếu thông tin cho câu hỏi thứ ' . ($index + 1),
                        'code' => 400,
                        'data' => null
                    ], 400);
                }
            }

            // Create questions with file URLs
            $insertedQuestions = [];
            DB::beginTransaction();
            try {
                foreach ($questions as $questionData) {
                    // Xử lý audio base64
                    $audioUrl = null;
                    if (!empty($questionData['audio'])) {
                        try {
                            $result = Cloudinary::upload($questionData['audio'], [
                                'folder' => 'toeic/audio',
                                'resource_type' => 'video',
                                'format' => 'mp3'
                            ]);
                            $audioUrl = $result->getSecurePath();
                        } catch (\Exception $e) {
                            throw new \Exception('Lỗi khi upload audio cho câu ' . $questionData['question_number'] . ': ' . $e->getMessage());
                        }
                    }

                    // Xử lý image base64
                    $imageUrl = null;
                    if (!empty($questionData['image'])) {
                        try {
                            $result = Cloudinary::upload($questionData['image'], [
                                'folder' => 'toeic/images',
                                'resource_type' => 'image',
                                'format' => 'jpg'
                            ]);
                            $imageUrl = $result->getSecurePath();
                        } catch (\Exception $e) {
                            throw new \Exception('Lỗi khi upload image cho câu ' . $questionData['question_number'] . ': ' . $e->getMessage());
                        }
                    }

                    $question = Question::create([
                        'exam_section_id' => $exam_id,
                        'question_number' => $questionData['question_number'],
                        'part_number' =>  $examSection->part_number,
                        'question_text' => $questionData['question'],
                        'option_a' => $questionData['answer_a'],
                        'option_b' => $questionData['answer_b'],
                        'option_c' => $questionData['answer_c'],
                        'option_d' => $questionData['answer_d'],
                        'correct_answer' => strtoupper($questionData['correct_answer']),
                        'explanation' => $questionData['explanation'],
                        'audio_url' => $audioUrl,
                        'image_url' => $imageUrl
                    ]);

                    $insertedQuestions[] = $question;
                }

                DB::commit();

                return response()->json([
                    'message' => 'Thêm câu hỏi thành công.',
                    'code' => 201,
                    'data' => [
                        'exam_section_id' => $exam_id,
                        'questions_count' => count($insertedQuestions),
                        'questions' => $insertedQuestions
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi thêm câu hỏi: ' . $e->getMessage(),
                'code' => 500,
                'data' => null
            ], 500);
        }
    }   
            
    /**
     * Lấy danh sách câu hỏi theo exam_section_id
     */
    public function getQuestionsByExamSection($exam_id)
    {
        try {
            // Validate exam section exists
            $examSection = ExamSection::where('id', $exam_id)
                ->where('is_deleted', false)
                ->select([
                    'id',
                    'exam_code',
                    'exam_name',
                    'section_name',
                    'part_number',
                    'question_count',
                    'duration',
                    'max_score'
                ])
                ->first();

            if (!$examSection) {
                return response()->json([
                    'message' => 'Không tìm thấy phần thi.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Lấy danh sách câu hỏi theo exam_section_id
            $questions = Question::where('exam_section_id', $exam_id)
                ->select([
                    'id',
                    'exam_section_id',
                    'question_number',
                    'part_number',
                    'question_text',
                    'option_a',
                    'option_b',
                    'option_c',
                    'option_d',
                    'correct_answer',
                    'explanation',
                    'audio_url',
                    'image_url'
                ])
                ->orderBy('question_number', 'asc')
                ->get();

            if ($questions->isEmpty()) {
                return response()->json([
                    'message' => 'Không tìm thấy câu hỏi nào cho phần thi này.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            return response()->json([
                'message' => 'Lấy danh sách câu hỏi thành công.',
                'code' => 200,
                'data' => [
                    'exam_section' => $examSection,
                    'questions' => $questions
                ],
                'meta' => [
                    'total_questions' => $questions->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách câu hỏi: ' . $e->getMessage(),
                'code' => 500,
                'data' => null
            ], 500);
        }
    }
}
