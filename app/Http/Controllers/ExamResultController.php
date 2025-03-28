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
        // Validate dữ liệu đầu vào
        $request->validate([
            'user_id' => 'required|integer|exists:User,id',
            'exam_code' => 'required|string',
            'parts' => 'required|array',
            'parts.*.part_number' => 'required|integer|between:1,7',
            'parts.*.answers' => 'required|array',
            'parts.*.answers.*.user_answer' => 'required|string',
            'parts.*.answers.*.correct_answer' => 'required|string',
        ]);

        // Khởi tạo mảng để lưu kết quả
        $results = [];

        // Duyệt qua từng phần
        foreach ($request->parts as $part) {
            $partNumber = $part['part_number'];
            $correctCount = 0;
            $wrongCount = 0;

            // Duyệt qua từng câu trả lời
            foreach ($part['answers'] as $answer) {
                if ($answer['user_answer'] === $answer['correct_answer']) {
                    $correctCount++;
                } else {
                    $wrongCount++;
                }
            }

            // Chỉ lưu kết quả nếu có câu trả lời
            if (count($part['answers']) > 0) {
                // Tính điểm cho phần thi
                $score = $correctCount * 5; // Mỗi câu đúng được 5 điểm

                // Lấy exam_section_id
                $examSectionId = $this->getExamSectionId($request->exam_code, $partNumber);

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
}
