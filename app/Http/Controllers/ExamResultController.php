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
        try {
            // Validate dữ liệu đầu vào
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'exam_code' => 'required|exists:ExamSection,exam_code',
                'answers' => 'required|array',
                'answers.*.part_number' => 'required|integer|between:1,7',
                'answers.*.questions' => 'required|array',
                'answers.*.questions.*.question_number' => 'required|integer',
                'answers.*.questions.*.user_answer' => 'required|string|in:A,B,C,D',
                'answers.*.questions.*.time_spent' => 'required|integer|min:0'
            ], [
                'user_id.required' => 'ID người dùng là bắt buộc.',
                'user_id.exists' => 'Người dùng không tồn tại.',
                'exam_code.required' => 'Mã bài thi là bắt buộc.',
                'exam_code.exists' => 'Bài thi không tồn tại.',
                'answers.required' => 'Danh sách câu trả lời là bắt buộc.',
                'answers.array' => 'Danh sách câu trả lời phải là một mảng.',
                'answers.*.part_number.required' => 'Số phần thi là bắt buộc.',
                'answers.*.part_number.integer' => 'Số phần thi phải là số nguyên.',
                'answers.*.part_number.between' => 'Số phần thi phải từ 1 đến 7.',
                'answers.*.questions.required' => 'Danh sách câu hỏi là bắt buộc.',
                'answers.*.questions.array' => 'Danh sách câu hỏi phải là một mảng.',
                'answers.*.questions.*.question_number.required' => 'Số câu hỏi là bắt buộc.',
                'answers.*.questions.*.question_number.integer' => 'Số câu hỏi phải là số nguyên.',
                'answers.*.questions.*.user_answer.required' => 'Câu trả lời là bắt buộc.',
                'answers.*.questions.*.user_answer.in' => 'Câu trả lời phải là A, B, C hoặc D.',
                'answers.*.questions.*.time_spent.required' => 'Thời gian làm bài là bắt buộc.',
                'answers.*.questions.*.time_spent.integer' => 'Thời gian làm bài phải là số nguyên.',
                'answers.*.questions.*.time_spent.min' => 'Thời gian làm bài phải lớn hơn hoặc bằng 0.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu đầu vào không hợp lệ.',
                    'code' => 400,
                    'data' => null,
                    'message_array' => $validator->errors()
                ], 400);
            }

            // Lấy thông tin bài thi
            $examSections = ExamSection::where('exam_code', $request->exam_code)
                ->where('is_deleted', false)
                ->get();

            if ($examSections->isEmpty()) {
                return response()->json([
                    'message' => 'Không tìm thấy bài thi.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Khởi tạo mảng kết quả cho từng phần
            $partScores = [
                1 => ['correct' => 0, 'total' => 0], // Listening
                2 => ['correct' => 0, 'total' => 0], // Listening
                3 => ['correct' => 0, 'total' => 0], // Listening
                4 => ['correct' => 0, 'total' => 0], // Listening
                5 => ['correct' => 0, 'total' => 0], // Reading
                6 => ['correct' => 0, 'total' => 0], // Reading
                7 => ['correct' => 0, 'total' => 0]  // Reading
            ];

            // Xử lý từng phần thi
            foreach ($request->answers as $partAnswer) {
                $partNumber = $partAnswer['part_number'];
                
                // Lấy thông tin phần thi
                $examSection = $examSections->firstWhere('part_number', $partNumber);
                if (!$examSection) continue;

                // Lấy danh sách câu hỏi của phần thi
                $questions = Question::where('exam_section_id', $examSection->id)
                    ->where('is_deleted', false)
                    ->get();

                // Xử lý từng câu trả lời
                foreach ($partAnswer['questions'] as $answer) {
                    $question = $questions->firstWhere('question_number', $answer['question_number']);
                    if (!$question) continue;

                    $partScores[$partNumber]['total']++;
                    if ($answer['user_answer'] === $question->correct_answer) {
                        $partScores[$partNumber]['correct']++;
                    }
                }
            }

            // Tính điểm cho từng phần
            $listeningScore = 0;
            $readingScore = 0;

            // Tính điểm Listening (Part 1-4)
            $listeningCorrect = 0;
            $listeningTotal = 0;
            for ($i = 1; $i <= 4; $i++) {
                $listeningCorrect += $partScores[$i]['correct'];
                $listeningTotal += $partScores[$i]['total'];
            }
            if ($listeningTotal > 0) {
                $listeningScore = round(($listeningCorrect / $listeningTotal) * 495);
            }

            // Tính điểm Reading (Part 5-7)
            $readingCorrect = 0;
            $readingTotal = 0;
            for ($i = 5; $i <= 7; $i++) {
                $readingCorrect += $partScores[$i]['correct'];
                $readingTotal += $partScores[$i]['total'];
            }
            if ($readingTotal > 0) {
                $readingScore = round(($readingCorrect / $readingTotal) * 495);
            }

            // Tính tổng điểm
            $totalScore = $listeningScore + $readingScore;

            // Lưu kết quả vào database
            $examResult = ExamResult::create([
                'user_id' => $request->user_id,
                'exam_code' => $request->exam_code,
                'total_score' => $totalScore,
                'listening_score' => $listeningScore,
                'reading_score' => $readingScore,
                'correct_answers' => json_encode($partScores),
                'answers' => json_encode($request->answers)
            ]);

            return response()->json([
                'message' => 'Nộp bài thi thành công.',
                'code' => 201,
                'data' => [
                    'exam_result_id' => $examResult->id,
                    'total_score' => $totalScore,
                    'listening_score' => $listeningScore,
                    'reading_score' => $readingScore,
                    'part_scores' => $partScores
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi nộp bài thi.',
                'code' => 500,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tính điểm TOEIC dựa trên số câu trả lời đúng
     * Đây là phiên bản đơn giản - bạn có thể thêm logic tính điểm phức tạp hơn
     */
    private function calculateTOEICScore($correctAnswers, $totalQuestions)
    {
        // Tính tỷ lệ phần trăm câu trả lời đúng
        $percentage = ($correctAnswers / $totalQuestions) * 100;
        // Chuyển đổi sang thang điểm TOEIC (0-990)
        return round($percentage * 10);
    }

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
}
