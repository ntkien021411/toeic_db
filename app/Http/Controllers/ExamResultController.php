<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ExamResult;
use App\Models\ExamSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Question;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\ExamAnswer;

class ExamResultController extends Controller
{
    /**
     * API nộp bài thi và tính điểm
     * 
     * Cấu trúc dữ liệu đầu vào:
     * {
     *   "user_id": 1,                    // ID của người dùng
     *   "exam_code": "TOEIC_001",        // Mã bài thi
     *   "answers": [                     // Mảng chứa câu trả lời theo từng phần
     *     {
     *       "part_number": 1,            // Số phần thi (1-7)
     *       "questions": [               // Mảng chứa câu trả lời của phần thi đó
     *         {
     *           "question_number": 1,     // Số thứ tự câu hỏi trong phần thi
     *           "user_answer": "A",      // Câu trả lời của người dùng (A,B,C,D)
     *           "time_spent": 30         // Thời gian làm bài (giây)
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function submitExam(Request $request)
    {
       // Tạo validator
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:User,id',
            'exam_code' => 'required|string|exists:Exam_Section,exam_code', // Kiểm tra xem exam_code có tồn tại trong bảng Exam_Section
            'parts' => 'required|array',
            'parts.*.part_number' => 'nullable|integer|between:1,7',
            'parts.*.answers' => 'nullable|array',
            'parts.*.answers.*.user_answer' => 'nullable|string',
            'parts.*.answers.*.question_number' => 'required|integer', // Đảm bảo question_number có mặt
        ], [
            'exam_code.exists' => 'Mã bài thi không tồn tại trong hệ thống.', // Tùy chỉnh thông báo lỗi
        ]);

        // Kiểm tra xem có lỗi không
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ.',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' => $validator->errors() // Trả về các lỗi
            ], 400);
        }
        // Khởi tạo mảng để lưu kết quả
        $results = [];

        // Duyệt qua từng phần
        foreach ($request->parts as $part) {
            $partNumber = $part['part_number'];
            $correctCount = 0;
            $wrongCount = 0;

            // Duyệt qua từng câu trả lời
            if (isset($part['answers'])) {
                foreach ($part['answers'] as $answer) {
                    // Kiểm tra nếu chỉ có một trong hai trường user_answer hoặc correct_answer
                    if ((isset($answer['user_answer']) && !isset($answer['correct_answer'])) || 
                        (!isset($answer['user_answer']) && isset($answer['correct_answer']))) {
                        $wrongCount++; // Tính là câu sai
                    } elseif (isset($answer['user_answer']) && isset($answer['correct_answer'])) {
                        if ($answer['user_answer'] === $answer['correct_answer']) {
                            $correctCount++;
                        } else {
                            $wrongCount++;
                        }
                    }

                    // Lưu câu trả lời vào bảng Exam_Answers
                    $examSectionId = $this->getExamSectionId($request->exam_code, $partNumber);
                    // Kiểm tra xem exam_section_id có hợp lệ không
                    if ($examSectionId === null) {
                        return response()->json([
                            'message' => 'Invalid exam section.',
                            'code' => 400,
                        ], 400);
                    }
                    ExamAnswer::create([
                        'user_id' => $request->user_id,
                        'exam_section_id' => $examSectionId,
                        'question_number' => $answer['question_number'],
                        'answer' => $answer['user_answer'],
                    ]);
                }
            }

            // Chỉ lưu kết quả nếu có câu trả lời
            if (count($part['answers'] ?? []) > 0) {
                // Tính điểm cho phần thi
                $score = $correctCount * 5; // Mỗi câu đúng được 5 điểm

                // Lấy exam_section_id
                $examSectionId = $this->getExamSectionId($request->exam_code, $partNumber);
                 // Kiểm tra xem exam_section_id có hợp lệ không
                 if ($examSectionId === null) {
                     return response()->json([
                         'message' => 'Invalid exam section.',
                         'code' => 400,
                     ], 400);
                 }
                ExamResult::create([
                    'user_id' => $request->user_id,
                    'exam_section_id' => $examSectionId,
                    'correct_answers' => $correctCount,
                    'wrong_answers' => $wrongCount,
                    'score' => $score,
                    'submitted_at' => now(), // Lưu thời gian nộp bài
                ]);

                // Thêm thông tin kết quả vào mảng $results
                $results[] = [
                    'part_number' => $partNumber,
                    'correct_answers' => $correctCount,
                    'wrong_answers' => $wrongCount,
                    'score' => $score,
                ];
            }
        }

        return response()->json([
            'message' => 'Nộp bài thi thành công.',
            'code' => 201,
            'data' => $results,
        ]);
    }

    // Hàm để lấy exam_section_id dựa trên exam_code và part_number
    private function getExamSectionId($examCode, $partNumber)
    {
        $examSection = ExamSection::where('exam_code', $examCode)
            ->where('part_number', $partNumber)
            ->first();

        return $examSection ? $examSection->id : null;
    }

    /**
     * Tính điểm TOEIC dựa trên số câu trả lời đúng
     * Đây là phiên bản đơn giản - bạn có thể thêm logic tính điểm phức tạp hơn
     */
    // private function calculateTOEICScore($correctAnswers, $totalQuestions)
    // {
    //     // Tính tỷ lệ phần trăm câu trả lời đúng
    //     $percentage = ($correctAnswers / $totalQuestions) * 100;
    //     // Chuyển đổi sang thang điểm TOEIC (0-990)
    //     return round($percentage * 10);
    // }

    public function getStatistics()
    {
        // Lấy tất cả các bài thi unique theo exam_code và part_number
        $examSections = ExamSection::select('id', 'exam_code', 'part_number')
            ->distinct()
            ->get();

        $statistics = [];

        foreach ($examSections as $section) {
            // Lấy kết quả bài thi cho từng exam_section_id
            $results = ExamResult::where('exam_section_id', $section->id)->get();

            // Khởi tạo mảng để chứa thông tin sinh viên
            $students = [];

            foreach ($results as $result) {
                // Lấy thông tin sinh viên
                $user = User::find($result->user_id);
                if ($user) {
                    $students[] = [
                        'user_id' => $user->id,
                        'user_name' => $user->full_name,
                        'correct_answers' => $result->correct_answers,
                        'wrong_answers' => $result->wrong_answers,
                        'score' => $result->score, // Giả sử bạn đã lưu tổng điểm trong bảng Exam_Result
                        'submitted_at' => $result->submitted_at, // Thêm thời gian nộp bài
                    ];
                }
            }

            // Thêm vào mảng thống kê
            $statistics[] = [
                'exam_code' => $section->exam_code,
                'part_number' => $section->part_number,
                'students' => $students,
            ];
        }

        return response()->json([
            'message' => 'Thống kê bài thi thành công.',
            'code' => 200,
            'data' => $statistics,
        ]);
    }

    public function getUserExamHistory($userId, Request $request)
    {
        // Thiết lập giá trị mặc định cho phân trang
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);

        // Lấy kết quả bài thi cho người dùng đã chỉ định với is_deleted = false
        $examResults = ExamResult::where('user_id', $userId)
            ->where('is_deleted', false) // Kiểm tra is_deleted
            ->with('examSection') // Eager load mối quan hệ exam section
            ->orderBy('submitted_at', 'desc') // Sắp xếp theo thời gian nộp
            ->get(); // Lấy tất cả kết quả để xử lý

        // Kiểm tra xem có kết quả nào không
        if ($examResults->isEmpty()) {
            return response()->json([
                'message' => 'Không tìm thấy kết quả bài thi cho người dùng này.',
                'code' => 404,
                'data' => null
            ], 404);
        }

        // Nhóm kết quả theo exam_code và submitted_at
        $formattedResults = [];
        foreach ($examResults as $result) {
            $examCode = $result->examSection->exam_code;
            $submittedAt = $result->submitted_at->format('Y-m-d H:i:s'); // Định dạng thời gian nộp

            // Tạo khóa duy nhất cho mỗi bài thi dựa trên exam_code và thời gian nộp
            $uniqueKey = $examCode . '_' . $submittedAt;

            // Kiểm tra xem đã có kết quả cho khóa này chưa
            if (!isset($formattedResults[$uniqueKey])) {
                $formattedResults[$uniqueKey] = [
                    'exam_date' => $result->submitted_at->format('H:i:s d-m-Y'), // Ngày làm bài
                    // 'exam_name' => $result->examSection->exam_, // Tên bài thi
                    'exam_code' => $examCode, // Mã bài thi
                    'exam_type' => 'By Part', // Dạng bài thi
                    'status' => 'DONE', // Trạng thái mặc định
                    'score' => $result->score, // Điểm
                    'parts' => [], // Mảng để lưu các phần thi
                ];
            }

            // Thêm thông tin phần thi vào mảng parts
            $formattedResults[$uniqueKey]['parts'][] = [
                'part_number' => (int)$result->examSection->part_number, // Đảm bảo part_number là kiểu số nguyên
                'correct_answers' => $result->correct_answers,
                'wrong_answers' => $result->wrong_answers,
                'score' => $result->score,
            ];
        }

        // Chuyển đổi mảng kết quả thành dạng mảng để trả về
        $formattedResults = array_values($formattedResults);

        // Lọc ra các bài thi đủ 7 phần
        $fullResults = [];
        foreach ($formattedResults as $result) {
            if (count($result['parts']) === 7) {
                $totalScore = array_sum(array_column($result['parts'], 'score')); // Tính tổng điểm
                $finalScore = min($totalScore, 990); // Giới hạn điểm tối đa là 990

                $fullResults[] = [
                    'exam_date' => $result['exam_date'],
                    'exam_name' => $result['exam_code'], // Thay exam_name bằng exam_code khi đủ 7 phần
                    'exam_code' => $result['exam_code'], // Lấy exam_code khi đủ 7 phần
                    'part_number' => 0, // Nếu đủ 7 phần, part_number là 0
                    'exam_type' => 'Full Test',
                    'status' => 'DONE',
                    'score' => $finalScore,
                ];
            } else {
                // Nếu chưa đủ 7 phần, giữ nguyên các kết quả
                foreach ($result['parts'] as $part) {
                    // Tìm exam_name từ exam_code và part_number
                    $examName = ExamSection::where('exam_code', $result['exam_code'])
                        ->where('part_number', $part['part_number'])
                        ->value('exam_name'); // Lấy tên bài thi tương ứng

                    $fullResults[] = [
                        'exam_date' => $result['exam_date'],
                        'exam_name' => $examName, // Lấy exam_name từ truy vấn
                        'exam_code' => $result['exam_code'], // Đảm bảo exam_code không null
                        'part_number' => (int)$part['part_number'], // Đảm bảo part_number là kiểu số nguyên
                        'exam_type' => 'By Part',
                        'status' => 'DONE',
                        'score' => $part['score'],
                    ];
                }
            }
        }

        // Paginate the formatted results
        $currentPage = $pageNumber;
        $currentPageResults = array_slice($fullResults, ($currentPage - 1) * $pageSize, $pageSize);
        $paginatedResults = new LengthAwarePaginator($currentPageResults, count($fullResults), $pageSize, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        // Custom pagination response format without 'data' key
        return response()->json([
            'message' => 'Lấy kết quả lịch sử thi thành công.',
            'code' => 200,
            'data' => $paginatedResults->items(),
            'meta' => [
                'total' => $paginatedResults->total(),
                'pageCurrent' => $paginatedResults->currentPage(),
                'pageSize' => $paginatedResults->perPage(),
                'totalPage' => $paginatedResults->lastPage()
            ]
        ], 200);
    }

    public function getExamDetails(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'exam_date' => 'required|string',
            'user_id' => 'required|integer|exists:User,id',
            'exam_code' => 'required|string|exists:Exam_Section,exam_code',
            'part_number' => 'required|integer|between:0,7',
        ], [
            'exam_code.exists' => 'Mã bài thi không tồn tại trong hệ thống.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ.',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' => $validator->errors()
            ], 400);
        }

        // Chuyển đổi exam_date thành định dạng datetime
        $examDate = \Carbon\Carbon::createFromFormat('H:i:s d-m-Y', $request->exam_date);
        $formattedExamDate = $examDate->format('Y-m-d H:i:s');

        $result = [];

        if ($request->part_number == 0) {
            // Lấy tất cả các part từ 1-7
            for ($part = 1; $part <= 7; $part++) {
                // Lấy exam_section_id cho mỗi part
                $examSectionId = ExamSection::where('exam_code', $request->exam_code)
                    ->where('part_number', $part)
                    ->value('id');

                if (!$examSectionId) {
                    continue; // Bỏ qua nếu không tìm thấy part
                }

                // Lấy tất cả câu hỏi của part
                $questions = Question::where('exam_section_id', $examSectionId)
                    ->where('is_deleted', false) // Chỉ lấy câu hỏi chưa bị xóa
                    ->orderBy('question_number')
                    ->get();

                // Lấy tất cả câu trả lời của người dùng cho part này
                $answers = ExamAnswer::where('user_id', $request->user_id)
                    ->where('exam_section_id', $examSectionId)
                    ->where('created_at', $formattedExamDate)
                    ->get();

                // Tạo mảng kết quả cho part này
                $partResult = [];
                foreach ($questions as $question) {
                    $userAnswer = $answers->where('question_number', $question->question_number)->first();
                    
                    $partResult[] = [
                        'question_number' => $question->question_number,
                        'question_text' => $question->question_text ?? 'N/A', // Đảm bảo không null
                        'option_a' => $question->option_a ?? 'N/A', // Đảm bảo không null
                        'option_b' => $question->option_b ?? 'N/A', // Đảm bảo không null
                        'option_c' => $question->option_c ?? 'N/A', // Đảm bảo không null
                        'option_d' => $question->option_d ?? 'N/A', // Đảm bảo không null
                        'correct_answer' => $question->correct_answer,
                        'user_answer' => $userAnswer ? $userAnswer->answer : null,
                        'is_correct' => $userAnswer ? ($userAnswer->answer === $question->correct_answer) : null,
                        'image_url' => $question->image_url ?? null, // Lấy image_url
                        'audio_url' => $question->audio_url ?? null, // Lấy audio_url
                        'explanation' => $question->explanation ?? null // Lấy explanation
                    ];
                }

                // Nhóm câu hỏi theo từng case
                $groupedQuestions = [];
                switch ($part) {
                    case 1:
                    case 5:
                        // Mỗi câu hỏi là một nhóm riêng
                        foreach ($partResult as $question) {
                            $groupedQuestions[] = $question;
                        }
                        break;

                    case 2:
                    case 3:
                    case 4:
                        // Nhóm 3 câu một
                        for ($i = 0; $i < count($partResult); $i += 3) {
                            $groupedQuestions[] = array_slice($partResult, $i, 3);
                        }
                        break;

                    case 6:
                        // Nhóm 4 câu một
                        for ($i = 0; $i < count($partResult); $i += 4) {
                            $groupedQuestions[] = array_slice($partResult, $i, 4);
                        }
                        break;

                    case 7:
                        // Xử lý đặc biệt cho part 7 với pattern tăng dần
                        $currentIndex = 0;
                        $pattern = [
                            2, 2, 2,     // 6 câu (3 nhóm 2)
                            3, 3, 3,     // 9 câu (3 nhóm 3)
                            4, 4, 4,     // 12 câu (3 nhóm 4)
                            4, 4, 4,     // 12 câu (3 nhóm 4)
                            5, 5, 5      // 15 câu (3 nhóm 5)
                        ];              // Tổng: 54 câu
                        $patternIndex = 0;
                        
                        while ($currentIndex < count($partResult)) {
                            $groupSize = $pattern[$patternIndex % count($pattern)];
                            $remainingQuestions = count($partResult) - $currentIndex;
                            
                            if ($groupSize > $remainingQuestions) {
                                $groupSize = $remainingQuestions;
                            }
                            
                            $groupedQuestions[] = array_slice($partResult, $currentIndex, $groupSize);
                            $currentIndex += $groupSize;
                            $patternIndex++;
                        }
                        break;
                }

                // Thêm kết quả của part vào mảng tổng hợp với key là số nguyên
                $result[$part] = $groupedQuestions; // Lưu nhóm câu hỏi đã được xử lý
            }
        } else {
            // Lấy một part cụ thể
            $examSectionId = ExamSection::where('exam_code', $request->exam_code)
                ->where('part_number', $request->part_number)
                ->value('id');

            if (!$examSectionId) {
                return response()->json([
                    'message' => 'Không tìm thấy phần thi.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Lấy tất cả câu hỏi của part
            $questions = Question::where('exam_section_id', $examSectionId)
                ->where('is_deleted', false) // Chỉ lấy câu hỏi chưa bị xóa
                ->orderBy('question_number')
                ->get();

            // Lấy tất cả câu trả lời của người dùng
            $answers = ExamAnswer::where('user_id', $request->user_id)
                ->where('exam_section_id', $examSectionId)
                ->where('created_at', $formattedExamDate)
                ->get();

            // Tạo mảng kết quả cho part cụ thể
            $partResult = [];
            foreach ($questions as $question) {
                $userAnswer = $answers->where('question_number', $question->question_number)->first();
                
                $partResult[] = [
                    'question_number' => $question->question_number,
                    'question_text' => $question->question_text ?? 'N/A', // Đảm bảo không null
                    'option_a' => $question->option_a ?? 'N/A', // Đảm bảo không null
                    'option_b' => $question->option_b ?? 'N/A', // Đảm bảo không null
                    'option_c' => $question->option_c ?? 'N/A', // Đảm bảo không null
                    'option_d' => $question->option_d ?? 'N/A', // Đảm bảo không null
                    'correct_answer' => $question->correct_answer,
                    'user_answer' => $userAnswer ? $userAnswer->answer : null,
                    'is_correct' => $userAnswer ? ($userAnswer->answer === $question->correct_answer) : null,
                    'image_url' => $question->image_url ?? null, // Lấy image_url
                    'audio_url' => $question->audio_url ?? null, // Lấy audio_url
                    'explanation' => $question->explanation ?? null // Lấy explanation
                ];
            }

            // Nhóm câu hỏi theo từng case cho part cụ thể
            $groupedQuestions = [];
            switch ($request->part_number) {
                case 1:
                case 5:
                    // Mỗi câu hỏi là một nhóm riêng
                    foreach ($partResult as $question) {
                        $groupedQuestions[] = $question;
                    }
                    break;

                case 2:
                case 3:
                case 4:
                    // Nhóm 3 câu một
                    for ($i = 0; $i < count($partResult); $i += 3) {
                        $groupedQuestions[] = array_slice($partResult, $i, 3);
                    }
                    break;

                case 6:
                    // Nhóm 4 câu một
                    for ($i = 0; $i < count($partResult); $i += 4) {
                        $groupedQuestions[] = array_slice($partResult, $i, 4);
                    }
                    break;

                case 7:
                    // Xử lý đặc biệt cho part 7 với pattern tăng dần
                    $currentIndex = 0;
                    $pattern = [
                        2, 2, 2,     // 6 câu (3 nhóm 2)
                        3, 3, 3,     // 9 câu (3 nhóm 3)
                        4, 4, 4,     // 12 câu (3 nhóm 4)
                        4, 4, 4,     // 12 câu (3 nhóm 4)
                        5, 5, 5      // 15 câu (3 nhóm 5)
                    ];              // Tổng: 54 câu
                    $patternIndex = 0;
                    
                    while ($currentIndex < count($partResult)) {
                        $groupSize = $pattern[$patternIndex % count($pattern)];
                        $remainingQuestions = count($partResult) - $currentIndex;
                        
                        if ($groupSize > $remainingQuestions) {
                            $groupSize = $remainingQuestions;
                        }
                        
                        $groupedQuestions[] = array_slice($partResult, $currentIndex, $groupSize);
                        $currentIndex += $groupSize;
                        $patternIndex++;
                    }
                    break;
            }

            // Thêm kết quả của part vào mảng tổng hợp với key là số nguyên
            $result[$request->part_number] = $groupedQuestions; // Lưu nhóm câu hỏi đã được xử lý
        }

        return response()->json([
            'message' => 'Lấy chi tiết câu trả lời thành công.',
            'code' => 200,
            'data' => $result
        ], 200);
    }
}
