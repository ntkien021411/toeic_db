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
            'duration' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    // Kiểm tra thời gian phù hợp với từng part
                    $maxDuration = [
                        1 => 6,    // Part 1: 6 phút
                        2 => 20,   // Part 2: 20 phút
                        3 => 30,   // Part 3: 30 phút
                        4 => 30,   // Part 4: 30 phút
                        5 => 25,   // Part 5: 25 phút
                        6 => 15,   // Part 6: 15 phút
                        7 => 55    // Part 7: 55 phút
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
                'year' => 1, // Set mặc định là 1
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
                ->select( 'year', 'type', 'is_Free')
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
                ->select('id', 'part_number', 'section_name', 'question_count', 'duration','max_score', 'is_Free','type')
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
                        'max_score' => $part->max_score,
                        'type' => $part->type,
                        'is_Free' => $part->is_Free
                    ];
                } elseif ($part->section_name === 'Reading' && $part->part_number >= 5 && $part->part_number <= 7) {
                    $existingParts['Reading'][$part->part_number] = [
                        'id' => $part->id,
                        'part_number' => $part->part_number,
                        'question_count' => $part->question_count,
                        'duration' => $part->duration,
                        'max_score' => $part->max_score,
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
                        'year' => $examInfo->year,
                        'type' => $examInfo->type,
                        'duration' => 120,
                        'max_score' => 990,
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

    public function getQuestionsByExamSection($exam_code, $part_number)
    {
        try {
            // Parse part_number to integer
            $part_number = (int) $part_number;

            // Validate input
            $validator = Validator::make([
                'exam_code' => $exam_code,
                'part_number' => $part_number
            ], [
                'exam_code' => 'required|string',
                'part_number' => 'required|integer|min:1|max:7'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu đầu vào không hợp lệ.',
                    'code' => 400,
                    'data' => null,
                    'errors' => $validator->errors()
                ], 400);
            }

            // Validate exam section exists
            $examSection = ExamSection::where('exam_code', $exam_code)
                ->where('part_number', $part_number)
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
            $questions = Question::where('exam_section_id', $examSection->id)
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

            // Xử lý nhóm câu hỏi theo part_number
            $groupedQuestions = [];
            $questionsArray = $questions->toArray();

            switch ($part_number) {
                case 1:
                case 5:
                    // Mỗi câu hỏi là một nhóm riêng
                    foreach ($questionsArray as $question) {
                        $groupedQuestions[] = [$question];
                    }
                    break;

                case 2:
                case 3:
                case 4:
                    // Nhóm 3 câu một
                    for ($i = 0; $i < count($questionsArray); $i += 3) {
                        $groupedQuestions[] = array_slice($questionsArray, $i, 3);
                    }
                    break;

                case 6:
                    // Nhóm 4 câu một
                    for ($i = 0; $i < count($questionsArray); $i += 4) {
                        $groupedQuestions[] = array_slice($questionsArray, $i, 4);
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
                    
                    while ($currentIndex < count($questionsArray)) {
                        $groupSize = $pattern[$patternIndex % count($pattern)];
                        $remainingQuestions = count($questionsArray) - $currentIndex;
                        
                        if ($groupSize > $remainingQuestions) {
                            $groupSize = $remainingQuestions;
                        }
                        
                        $groupedQuestions[] = array_slice($questionsArray, $currentIndex, $groupSize);
                        $currentIndex += $groupSize;
                        $patternIndex++;
                    }
                    break;
            }

        return response()->json([
                'message' => 'Lấy danh sách câu hỏi thành công.',
                'code' => 200,
                'data' => [
                    'exam_section' => $examSection,
                    'questions' => $groupedQuestions
                ],
                'meta' => [
                    'total_questions' => $questions->count(),
                    'total_groups' => count($groupedQuestions)
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
    
    public function listExamSections($exam_code)
    {
        try {
            // Validate exam_code
            if (empty($exam_code)) {
                return response()->json([
                    'message' => 'Mã bài thi không được để trống.',
                    'code' => 400,
                    'data' => null
                ], 400);
            }

            // Lấy thông tin chung của bài thi
            $examInfo = ExamSection::where('exam_code', $exam_code)
                ->where('is_deleted', false)
                ->select('exam_name', 'year', 'type', 'is_Free')
                ->first();

            if (!$examInfo) {
                return response()->json([
                    'message' => 'Không tìm thấy bài thi với mã này.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Lấy danh sách các phần thi
            $examSections = ExamSection::where('exam_code', $exam_code)
                ->where('is_deleted', false)
                ->select([
                    'id',
                    'exam_code',
                    'exam_name',
                    'section_name',
                    'part_number',
                    'question_count',
                    'duration',
                    'max_score',
                    'type',
                    'year',
                    'is_Free'
                ])
                ->orderBy('part_number', 'asc')
                ->get();

            // Phân loại theo section (Listening/Reading)
            $sections = [
                'Listening' => [],
                'Reading' => []
            ];

            foreach ($examSections as $section) {
                if ($section->section_name === 'Listening') {
                    $sections['Listening'][] = [
                        'id' => $section->id,
                        'part_number' => $section->part_number,
                        'question_count' => $section->question_count,
                        'duration' => $section->duration,
                        'max_score' => $section->max_score,
                        'is_Free' => $section->is_Free
                    ];
                } else {
                    $sections['Reading'][] = [
                        'id' => $section->id,
                        'part_number' => $section->part_number,
                        'question_count' => $section->question_count,
                        'duration' => $section->duration,
                        'max_score' => $section->max_score,
                        'is_Free' => $section->is_Free
                    ];
                }
            }

            return response()->json([
                'message' => 'Lấy danh sách phần thi thành công.',
                'code' => 200,
                'data' => [
                    'exam_info' => [
                        'exam_code' => $exam_code,
                        'exam_name' => $examInfo->exam_name,
                        'year' => $examInfo->year,
                        'type' => $examInfo->type,
                        'is_Free' => $examInfo->is_Free,
                        'total_duration' => 120,
                        'total_questions' => 200,
                        'total_max_score' => 990
                    ],
                    'sections' => $sections
                ],
                'meta' => [
                    'total_sections' => count($examSections),
                    'listening_parts' => count($sections['Listening']),
                    'reading_parts' => count($sections['Reading'])
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách phần thi: ' . $e->getMessage(),
                'code' => 500,
                'data' => null
            ], 500);
        }
    }

    /**
     * Lấy danh sách tất cả các exam section
     */
    public function getAllExamSections(Request $request)
    {
        try {
            $pageNumber = $request->input('pageNumber', 1);
            $pageSize = $request->input('pageSize', 10);

            $query = ExamSection::where('is_deleted', false)
                ->select([
                    'id',
                    'exam_code',
                    'exam_name',
                    'section_name',
                    'part_number',
                    'question_count',
                    'year',
                    'duration',
                    'max_score',
                    'type',
                    'is_Free'
                ])
                ->groupBy('exam_code', 'id', 'exam_name', 'section_name', 'part_number', 'question_count', 'year', 'duration', 'max_score', 'type', 'is_Free');

            // Phân trang dữ liệu
            $examSections = $query->paginate($pageSize, ['*'], 'page', $pageNumber);

            return response()->json([
                'message' => 'Lấy danh sách exam section thành công',
                'code' => 200,
                'data' => $examSections->items(),
                'meta' =>  $examSections->total() > 0 ? [
                    'total' => $examSections->total(),
                    'pageCurrent' => $examSections->currentPage(),
                    'pageSize' => $examSections->perPage(),
                    'totalPage' => $examSections->lastPage()
                ] : null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách exam section',
                'code' => 500,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy danh sách câu hỏi theo exam_code, nhóm theo part
     * 
     * @param string $examCode Mã bài thi
     */
    public function getQuestionsByExamCode($examCode)
    {
        try {
            // Lấy tất cả các part của bài thi
            $examSections = ExamSection::where('exam_code', $examCode)
                ->where('is_deleted', false)
                ->get();

            if ($examSections->isEmpty()) {
                return response()->json([
                    'message' => 'Không tìm thấy bài thi',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Khởi tạo mảng kết quả với 7 part rỗng
            $result = [
                1 => [], // Listening
                2 => [], // Listening
                3 => [], // Listening
                4 => [], // Listening
                5 => [], // Reading
                6 => [], // Reading
                7 => []  // Reading
            ];

            // Lấy câu hỏi cho từng part
            foreach ($examSections as $section) {
                $partNumber = $section->part_number;
                if (isset($result[$partNumber])) {
                    // Lấy câu hỏi của part này
                    $questions = Question::where('exam_section_id', $section->id)
                        ->where('is_deleted', false)
                        ->orderBy('question_number')
                        ->get();

                    // Thêm câu hỏi vào kết quả
                    foreach ($questions as $question) {
                        $result[$partNumber][] = [
                            'question_number' => $question->question_number,
                            'question_text' => $question->question_text,
                            'options' => [
                                'A' => $question->option_a,
                                'B' => $question->option_b,
                                'C' => $question->option_c,
                                'D' => $question->option_d
                            ],
                            'correct_answer' => $question->correct_answer,
                            'explanation' => $question->explanation,
                            'audio_url' => $question->audio_url,
                            'image_url' => $question->image_url
                        ];
                    }
                }
            }

            return response()->json([
                'message' => 'Lấy danh sách câu hỏi thành công',
                'code' => 200,
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách câu hỏi',
                'code' => 500,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin exam section
     * 
     * @param Request $request
     * @param int $id ID của exam section cần cập nhật
     */
    public function editExamSection(Request $request, $id)
    {
        try {
            // Tìm exam section cần cập nhật
            $examSection = ExamSection::find($id);
            if (!$examSection) {
                return response()->json([
                    'message' => 'Không tìm thấy exam section.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Validate dữ liệu đầu vào
            $validator = Validator::make($request->all(), [
                'exam_name' => 'required|string',
                'section_name' => [
                    'required',
                    'in:Listening,Reading',
                    function ($attribute, $value, $fail) use ($examSection) {
                        // Kiểm tra section_name phù hợp với part_number hiện tại
                        if ($examSection->part_number >= 1 && $examSection->part_number <= 4 && $value !== 'Listening') {
                            $fail('Part 1-4 phải thuộc phần Listening.');
                        }
                        if ($examSection->part_number >= 5 && $examSection->part_number <= 7 && $value !== 'Reading') {
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
                'duration' => [
                    'required',
                    'integer',
                    'min:1',
                    function ($attribute, $value, $fail) use ($request) {
                        // Kiểm tra thời gian phù hợp với từng part
                        $maxDuration = [
                            1 => 6,    // Part 1: 6 phút
                            2 => 20,   // Part 2: 20 phút
                            3 => 30,   // Part 3: 30 phút
                            4 => 30,   // Part 4: 30 phút
                            5 => 25,   // Part 5: 25 phút
                            6 => 15,   // Part 6: 15 phút
                            7 => 55    // Part 7: 55 phút
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
                'exam_name.required' => 'Tên bài thi là bắt buộc.',
                'section_name.required' => 'Tên phần thi là bắt buộc.',
                'section_name.in' => 'Phần thi phải là: Listening hoặc Reading.',
                'part_number.required' => 'Số phần là bắt buộc.',
                'part_number.integer' => 'Số phần phải là số nguyên.',
                'part_number.between' => 'Số phần phải từ 1 đến 7.',
                'question_count.required' => 'Số câu hỏi là bắt buộc.',
                'question_count.integer' => 'Số câu hỏi phải là số nguyên.',
                'question_count.min' => 'Số câu hỏi phải lớn hơn 0.',
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
                    'message_array' => $validator->errors()
                ], 400);
            }

            // Cập nhật thông tin exam section
            $examSection->update([
                'exam_name' => $request->exam_name,
                'section_name' => $request->section_name,
                'part_number' => $request->part_number,
                'question_count' => $request->question_count,
                'duration' => $request->duration,
                'max_score' => $request->max_score,
                'type' => $request->type,
                'is_Free' => $request->is_Free
            ]);

            return response()->json([
                'message' => 'Cập nhật exam section thành công.',
                'code' => 200,
                'data' => [
                    'id' => $examSection->id,
                    'exam_code' => $examSection->exam_code,
                    'exam_name' => $examSection->exam_name,
                    'section_name' => $examSection->section_name,
                    'part_number' => $examSection->part_number,
                    'question_count' => $examSection->question_count,
                    'duration' => $examSection->duration,
                    'max_score' => $examSection->max_score,
                    'type' => $examSection->type,
                    'is_Free' => $examSection->is_Free
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật exam section.',
                'code' => 500,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa nhiều exam section
     * 
     * @param Request $request Chứa mảng id của các exam section cần xóa
     */
    public function deleteExamSections(Request $request)
    {
        try {
            // Validate dữ liệu đầu vào
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'required|integer'
            ], [
                'ids.required' => 'Danh sách ID là bắt buộc.',
                'ids.array' => 'Danh sách ID phải là một mảng.',
                'ids.*.required' => 'ID không được để trống.',
                'ids.*.integer' => 'ID phải là số nguyên.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu đầu vào không hợp lệ.',
                    'code' => 400,
                    'data' => null,
                    'message_array' => $validator->errors()
                ], 400);
            }

            // Lấy danh sách ID tồn tại và chưa bị xóa
            $existingIds = ExamSection::whereIn('id', $request->ids)
                ->where('is_deleted', false)
                ->pluck('id')
                ->toArray();

            if (empty($existingIds)) {
                return response()->json([
                    'message' => 'Không tìm thấy exam section nào để xóa.',
                    'code' => 404,
                    'data' => null
                ], 404);
            }

            // Cập nhật trạng thái xóa cho các exam section tồn tại
            $deletedCount = ExamSection::whereIn('id', $existingIds)
                ->update([
                    'is_deleted' => true,
                    'deleted_at' => now()
                ]);

            // Phân loại kết quả
            $result = [
                'deleted_ids' => $existingIds,
                'skipped_ids' => array_diff($request->ids, $existingIds)
            ];

            return response()->json([
                'message' => 'Xóa exam section thành công.',
                'code' => 200,
                'data' => [
                    'deleted_count' => $deletedCount,
                    'result' => $result
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa exam section.',
                'code' => 500,
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}



