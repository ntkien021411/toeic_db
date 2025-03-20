<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\ExamSection;
use App\Models\Question;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class ExamSectionController extends Controller
{
    public function listExam(Request $request)
    {
        try {
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);

            // Lấy các exam_code có đủ 7 phần
            $completeExamCodes = ExamSection::select('exam_code')
                ->where('is_deleted', false)
                ->groupBy('exam_code')
                ->havingRaw('COUNT(DISTINCT part_number) = 7')
                ->pluck('exam_code');

            // Lấy thông tin của exam đầu tiên trong mỗi nhóm có cùng exam_code
            $query = ExamSection::whereIn('exam_code', $completeExamCodes)
                ->where('is_deleted', false)
                ->groupBy('exam_code')
                ->select(
                    'exam_code',
                    DB::raw('MIN(exam_name) as exam_name'), // Lấy tên chung của exam
                    DB::raw('SUM(duration) as total_duration'),
                    DB::raw('SUM(question_count) as total_questions'),
                    DB::raw('SUM(max_score) as total_max_score'),
                    DB::raw('MIN(type) as type'),
                    DB::raw('MIN(year) as year'),
                    DB::raw('MIN(is_Free) as is_Free')
                );

        // Phân trang dữ liệu
            $exams = $query->paginate($pageSize);

        // Chuyển đổi dữ liệu theo format mong muốn
        $formattedExams = $exams->map(function ($exam) {
                // Xử lý tên exam để bỏ phần "Part X - ..."
                $examName = preg_replace('/\s*Part\s+\d+\s*-\s*.+$/', '', $exam->exam_name);
                
            return [
                    'exam_code' => $exam->exam_code,
                    'title' => $examName,
                    'duration' => 120,
                    'parts' => 'Full',
                    'questions' => 200,
                    'maxScore' => 990,
                'label' => $exam->type,
                    'isFree' => $exam->is_Free,
                    'year' => $exam->year
            ];
        });

        return response()->json([
            'message' => 'Lấy danh sách bài thi thành công',
            'code' => 200,
            'data' => $formattedExams,
            'meta' => $exams->total() > 0 ? [
                'total' => $exams->total(),
                'pageCurrent' => $exams->currentPage(),
                'pageSize' => $exams->perPage(),
                'totalPage' => $exams->lastPage()
            ] : null
        ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách bài thi.',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
    


    public function createExamSection(Request $request)
    {
        // Kiểm tra exam_code và part_number đã tồn tại chưa
        $existingExam = ExamSection::where('exam_code', $request->exam_code)
            ->where('part_number', $request->part_number)
        ->where('is_deleted', false)
        ->first();
        
        if ($existingExam) {
            return response()->json([
                'message' => 'Dữ liệu đầu vào không hợp lệ.',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' => [
                    'exam_code' => ['Mã bài thi và phần thi này đã tồn tại.']
                ]
            ], 400);
        }

        // Validate đầu vào
        $validator = Validator::make($request->all(), [
            'exam_code' => 'required|string',
            'exam_name' => 'required|string',
            'section_name' => [
                'required',
                'in:Listening,Reading',
                function ($attribute, $value, $fail) use ($request) {
                    // Kiểm tra section_name phù hợp với part_number
                    if ($request->part_number >= 1 && $request->part_number <= 4 && $value !== 'Listening') {
                        $fail('Part 1-4 phải thuộc phần Listening.');
                    }
                    if ($request->part_number >= 5 && $request->part_number <= 7 && $value !== 'Reading') {
                        $fail('Part 5-7 phải thuộc phần Reading.');
                    }
                }
            ],
            'part_number' => 'required|integer|between:1,7',
            'question_count' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    // Số câu hỏi cố định cho từng part
                    $requiredQuestions = [
                        1 => 6,    // Part 1: 6 câu
                        2 => 25,   // Part 2: 25 câu
                        3 => 39,   // Part 3: 39 câu
                        4 => 30,   // Part 4: 30 câu
                        5 => 30,   // Part 5: 30 câu
                        6 => 16,   // Part 6: 16 câu
                        7 => 54    // Part 7: 54 câu
                    ];
                    
                    if (isset($requiredQuestions[$request->part_number]) && $value !== $requiredQuestions[$request->part_number]) {
                        $fail("Part {$request->part_number} phải có chính xác {$requiredQuestions[$request->part_number]} câu hỏi.");
                    }
                }
            ],
            'year' => 'required|integer|min:2000',
            'duration' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    // Kiểm tra thời gian phù hợp với từng part
                    $maxDuration = [
                        1 => 6,
                        2 => 20,
                        3 => 30,
                        4 => 30,
                        5 => 25,
                        6 => 15,
                        7 => 55
                    ];
                    
                    if (isset($maxDuration[$request->part_number]) && $value > $maxDuration[$request->part_number]) {
                        $fail("Part {$request->part_number} không thể có thời gian làm bài nhiều hơn {$maxDuration[$request->part_number]} phút.");
                    }
                }
            ],
            'max_score' => 'required|integer|min:1',
            'type' => 'required|string',
            'is_Free' => 'required|boolean'
        ], [
            'exam_code.required' => 'Mã bài thi là bắt buộc.',
            'exam_name.required' => 'Tên bài thi là bắt buộc.',
            'section_name.required' => 'Tên phần thi là bắt buộc.',
            'section_name.in' => 'Phần thi phải là: Listening hoặc Reading.',
            'part_number.required' => 'Số phần là bắt buộc.',
            'part_number.integer' => 'Số phần phải là số nguyên.',
            'part_number.between' => 'Số phần phải từ 1 đến 7.',
            'question_count.required' => 'Số câu hỏi là bắt buộc.',
            'question_count.integer' => 'Số câu hỏi phải là số nguyên.',
            'question_count.min' => 'Số câu hỏi phải lớn hơn 0.',
            'year.required' => 'Năm là bắt buộc.',
            'year.integer' => 'Năm phải là số nguyên.',
            'year.min' => 'Năm phải từ 2000 trở lên.',
            'duration.required' => 'Thời gian làm bài là bắt buộc.',
            'duration.integer' => 'Thời gian làm bài phải là số nguyên.',
            'duration.min' => 'Thời gian làm bài phải lớn hơn 0.',
            'max_score.required' => 'Điểm tối đa là bắt buộc.',
            'max_score.integer' => 'Điểm tối đa phải là số nguyên.',
            'max_score.min' => 'Điểm tối đa phải lớn hơn 0.',
            'type.required' => 'Loại bài thi là bắt buộc.',
            'is_Free.required' => 'Trạng thái miễn phí là bắt buộc.',
            'is_Free.boolean' => 'Trạng thái miễn phí phải là true hoặc false.'
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

        try {
            // Tạo exam section mới
        $examSection = ExamSection::create([
                'exam_code' => $request->exam_code,
                'exam_name' => $request->exam_name,
                'section_name' => $request->section_name,
                'part_number' => $request->part_number,
                'question_count' => $request->question_count,
                'year' => $request->year,
                'duration' => $request->duration,
                'max_score' => $request->max_score,
                'type' => $request->type,
                'is_Free' => $request->is_Free
            ]);

            // Kiểm tra các part còn thiếu
            $existingParts = ExamSection::where('exam_code', $request->exam_code)
                ->where('is_deleted', false)
                ->pluck('part_number')
                ->toArray();

            $allParts = range(1, 7);
            $missingParts = array_diff($allParts, $existingParts);

            // Phân loại part còn thiếu theo section
            $missingPartsInfo = [
                'Listening' => array_filter($missingParts, fn($part) => $part >= 1 && $part <= 4),
                'Reading' => array_filter($missingParts, fn($part) => $part >= 5 && $part <= 7)
            ];

        return response()->json([
                'message' => 'Tạo bài thi thành công.',
                'code' => 201,
                'data' => [
                    'id' => $examSection->id,
                    'exam_code' => $examSection->exam_code,
                    'exam_name' => $examSection->exam_name,
                    'section_name' => $examSection->section_name,
                    'part_number' => $examSection->part_number,
                    'question_count' => $examSection->question_count,
                    'year' => $examSection->year,
                    'duration' => $examSection->duration,
                    'max_score' => $examSection->max_score,
                    'type' => $examSection->type,
                    'is_Free' => $examSection->is_Free,
                    'missing_parts' => [
                        'Listening' => array_values($missingPartsInfo['Listening']),
                        'Reading' => array_values($missingPartsInfo['Reading'])
                    ]
                ],
                'meta' => null
        ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi tạo bài thi.',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    public function checkExamParts($exam_code)
    {
        try {
            // Kiểm tra exam_code có tồn tại không và lấy thông tin chung của bài thi
            $examInfo = ExamSection::where('exam_code', $exam_code)
            ->where('is_deleted', false)
                ->select('exam_name', 'year', 'type', 'is_Free')
            ->first();
        
            if (!$examInfo) {
            return response()->json([
                    'message' => 'Mã bài thi không tồn tại.',
                    'code' => 404,
                    'data' => null,
                    'meta' => null
            ], 404);
        }

            // Lấy tất cả parts của exam này
            $parts = ExamSection::where('exam_code', $exam_code)
                ->where('is_deleted', false)
                ->select('id', 'part_number', 'section_name', 'question_count', 'duration', 'is_Free','type')
                ->get();

            // Khởi tạo cấu trúc parts với tất cả các part
            $existingParts = [
                'Listening' => [
                    1 => [],
                    2 => [],
                    3 => [],
                    4 => []
                ],
                'Reading' => [
                    5 => [],
                    6 => [],
                    7 => []
                ]
            ];

            // Điền dữ liệu cho các part tồn tại
            foreach ($parts as $part) {
                if ($part->section_name === 'Listening' && $part->part_number >= 1 && $part->part_number <= 4) {
                    $existingParts['Listening'][$part->part_number] = [
                        'id' => $part->id,
                        'part_number' => $part->part_number,
                        'question_count' => $part->question_count,
                        'duration' => $part->duration,
                        'type' => $part->type,
                        'is_Free' => $part->is_Free
                    ];
                } elseif ($part->section_name === 'Reading' && $part->part_number >= 5 && $part->part_number <= 7) {
                    $existingParts['Reading'][$part->part_number] = [
                        'id' => $part->id,
                        'part_number' => $part->part_number,
                        'question_count' => $part->question_count,
                        'duration' => $part->duration,
                        'type' => $part->type,
                        'is_Free' => $part->is_Free
                    ];
                }
            }

            return response()->json([
                'message' => 'Lấy thông tin bài thi thành công.',
                'code' => 200,
                'data' => [
                    'exam_info' => [
                        'exam_code' => $exam_code,
                        'exam_name' => $examInfo->exam_name,
                        'year' => $examInfo->year,
                        'type' => $examInfo->type,
                        'is_Free' => $examInfo->is_Free
                    ],
                    'parts_info' => $existingParts
                    
                ],
                'meta' => null
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi kiểm tra thông tin bài thi.',
                'code' => 500,
                'data' => null,
                'meta' => null,
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}



