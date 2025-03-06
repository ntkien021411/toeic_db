<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\ExamSection;
use App\Models\Question;
use Illuminate\Support\Facades\Validator;


class ExamSectionController extends Controller
{
    public function listExam(Request $request)
    {
        $pageNumber = $request->input('pageNumber', 1);
        $pageSize = $request->input('pageSize', 10);

       
        $query = ExamSection::query()
            ->where('part_number', 'Full')
            ->where('is_deleted', false); 

        // Phân trang dữ liệu
        $exams= $query->paginate($pageSize, [
            'id', 'exam_name', 
            'duration',
            'section_name',
            'part_number',
            'question_count',
            'max_score', 'type', 'is_Free'], 'page', $pageNumber);

        // Chuyển đổi dữ liệu theo format mong muốn
        $formattedExams = $exams->map(function ($exam) {
            return [
                'id' => $exam->id,
                'title' => $exam->exam_name,
                'duration' => $exam->duration,
                'parts' => '7',
                'questions' => $exam->question_count,
                'maxScore' => $exam->max_score, 
                'label' => $exam->type,
                'isFree' => $exam->is_Free
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
    }
    

    // Thông tin chi tiết
    public function detail($exam_id)
    {
        // Tìm ExamSection theo ID và load danh sách câu hỏi
        $examSection = ExamSection::with(['questions' => function ($query) {
            $query->where('is_deleted', false);
        }])
        ->where('id', $exam_id)
        ->where('is_deleted', false)
        ->first();
        
        // Kiểm tra nếu không tìm thấy dữ liệu
        if (!$examSection) {
            return response()->json([
                'message' => 'Không tìm thấy bài luyện thi',
                'code' => 404,
                'data' => null,
                'meta' => null
            ], 404);
        }

        // Trả về response
        return response()->json([
            'message' => 'Lấy thông tin chi tiết bài luyện thi thành công',
            'code' => 200,
            'data' => $examSection,
            'meta' => null
        ], 200);
    }



    // Thêm bài Luyện thi
    public function store(Request $request)
    {
        // Validate dữ liệu đầu vào với thông báo lỗi tùy chỉnh
        $validator = Validator::make($request->all(), [
            'exam_code'     => 'required|string|max:50',
            'exam_name'     => 'nullable|string|max:255',
            'section_name'  => 'required|in:Listening,Reading,Full',
            'part_number'   => 'required|in:1,2,3,4,5,6,7,Full',
            'question_count'=> 'nullable|integer|min:1',
            'year'          => 'nullable|integer|min:1',
            'duration'      => 'nullable|integer|min:1',
            'max_score'     => 'nullable|integer|min:1',
            'questions'     => 'required|array|min:1',
            'questions.*.image_url'     => 'nullable|string',
            'questions.*.question_number'     => 'nullable|integer',
            'questions.*.audio_url'     => 'nullable|string',
            'questions.*.part_number'   => 'required|in:1,2,3,4,5,6,7',
            'questions.*.question_text' => 'nullable|string',
            'questions.*.option_a'      => 'nullable|string',
            'questions.*.option_b'      => 'nullable|string',
            'questions.*.option_c'      => 'nullable|string',
            'questions.*.option_d'      => 'nullable|string',
            'questions.*.correct_answer'=> 'required|in:A,B,C,D',
        ], [
            'exam_code.required'     => 'Mã bài thi không được để trống.',
            'exam_code.max'          => 'Mã bài thi không được dài quá 50 ký tự.',
            'section_name.required'  => 'Vui lòng chọn loại phần thi.',
            'section_name.in'        => 'Loại phần thi không hợp lệ.',
            'part_number.required'   => 'Vui lòng chọn phần thi.',
            'part_number.in'         => 'Phần thi không hợp lệ.',
            'questions.required'     => 'Danh sách câu hỏi không được để trống.',
            'questions.array'        => 'Danh sách câu hỏi phải là một mảng.',
            'questions.min'          => 'Bài luyện thi phải có ít nhất một câu hỏi.',
            'questions.*.part_number.required'   => 'Mỗi câu hỏi phải có số phần thi.',
            'questions.*.part_number.in'         => 'Số phần thi không hợp lệ.',
            'questions.*.correct_answer.required'=> 'Mỗi câu hỏi phải có đáp án đúng.',
            'questions.*.correct_answer.in'      => 'Đáp án đúng chỉ có thể là A, B, C hoặc D.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu nhập vào không hợp lệ',
                'code' => 400,
                'data' => null,
                'meta' => null,
                'message_array' => $validator->errors()
            ], 400);
        }

        // Lấy dữ liệu hợp lệ
        $validatedData = $validator->validated();

        // Tạo mới Exam Section
        $examSection = ExamSection::create([
            'exam_code'     => $validatedData['exam_code'],
            'exam_name'     => $validatedData['exam_name'] ?? null,
            'section_name'  => $validatedData['section_name'],
            'part_number'   => $validatedData['part_number'],
            'question_count'=> $validatedData['question_count'] ?? null,
            'year'          => $validatedData['year'] ?? null,
            'duration'      => $validatedData['duration'] ?? null,
            'max_score'     => $validatedData['max_score'] ?? null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Chuẩn bị dữ liệu câu hỏi để insert nhanh (bulk insert)
        $questionsData = array_map(function ($question) use ($examSection) {
            return [
                'exam_section_id' => $examSection->id,
                'image_url'       => $question['image_url'] ?? null,
                'question_number'       => $question['question_number'] ?? null,
                'audio_url'       => $question['audio_url'] ?? null,
                'part_number'     => $question['part_number'],
                'question_text'   => $question['question_text'] ?? null,
                'option_a'        => $question['option_a'] ?? null,
                'option_b'        => $question['option_b'] ?? null,
                'option_c'        => $question['option_c'] ?? null,
                'option_d'        => $question['option_d'] ?? null,
                'correct_answer'  => $question['correct_answer'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }, $validatedData['questions']);

        // Chèn tất cả câu hỏi vào database một lần (bulk insert)
        Question::insert($questionsData);

        return response()->json([
            'message' => 'Tạo mới bài luyện thi thành công',
            'code'    => 201,
            'data'    => $examSection, 
            'meta'    => null
        ], 201);
    }


    public function update(Request $request, $exam_section_id)
    {
          // Sử dụng Validator để kiểm tra dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'exam_code'     => 'nullable|string|max:50',
            'exam_name'     => 'nullable|string|max:255',
            'section_name'  => 'required|in:Listening,Reading,Full',
            'part_number'   => 'required|in:1,2,3,4,5,6,7,Full',
            'question_count'=> 'nullable|integer|min:1',
            'year'          => 'nullable|integer|min:1',
            'duration'      => 'nullable|integer|min:1',
            'max_score'     => 'nullable|integer|min:1',
            'questions'     => 'required|array|min:1',
            'questions.*.image_url'     => 'nullable|string',
            'questions.*.question_number'     => 'nullable|integer',
            'questions.*.audio_url'     => 'nullable|string',
            'questions.*.part_number'   => 'required|in:1,2,3,4,5,6,7',
            'questions.*.question_text' => 'nullable|string',
            'questions.*.option_a'      => 'nullable|string',
            'questions.*.option_b'      => 'nullable|string',
            'questions.*.option_c'      => 'nullable|string',
            'questions.*.option_d'      => 'nullable|string',
            'questions.*.correct_answer'=> 'required|in:A,B,C,D',
            'questions.*.is_deleted'      => 'nullable|boolean',
            'questions.*.id'      => 'nullable|integer'
        ]);

        // Kiểm tra nếu validate thất bại
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
                'code'    => 422
            ], 422);
        }

        // Lấy dữ liệu đã được xác thực
        $validatedData = $validator->validated();

        // Tìm ExamSection
        $examSection = ExamSection::where('id', $exam_section_id)
            ->where('is_deleted', false)
            ->first();
        
        if (!$examSection) {
            return response()->json([
                'message' => 'Không tìm thấy bài luyện thi',
                'code'    => 404,
                'data'    => null,
                'meta'    => null
            ], 404);
        }

        // Nếu đánh dấu xóa, cập nhật trạng thái và trả về response
        if ($request->has('is_deleted') && $request->is_deleted == true) {
            $examSection->update([
                'deleted_at' => now(),
                'is_deleted' => true
            ]);

            Question::where('exam_section_id', $exam_section_id)
                ->update(['is_deleted' => true, 'deleted_at' => now()]);

            return response()->json([
                'message' => 'Xóa bài luyện thi thành công',
                'code'    => 200,
                'data'    => null,
                'meta'    => null
            ], 200);
        }

        // Cập nhật ExamSection
        $examSection->update($request->only([
            'exam_code', 'exam_name', 'section_name', 'part_number',
            'question_count', 'year', 'duration', 'max_score'
        ]));
        
         // Chuyển danh sách câu hỏi thành Collection
         $questions = collect($validatedData['questions']); // Lọc chỉ lấy những câu hỏi chưa bị xóa
       
        // 🔥 Lọc danh sách câu hỏi cần xóa (is_deleted = true)
        $deleteIds = $questions->where('is_deleted', true)->pluck('id')->filter()->toArray();
     

            // 👉 Xóa câu hỏi nếu có ID hợp lệ
        if (!empty($deleteIds)) {
            Question::whereIn('id', $deleteIds)->update([
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);
        }

        // 🔥 Lọc danh sách câu hỏi cần cập nhật hoặc thêm mới (is_deleted != true)
        $questionsToUpdate = $questions->reject(fn($q) => ($q['is_deleted'] ?? false) == true);

        // 👉 Cập nhật hoặc thêm mới câu hỏi
        $questionsToUpdate->each(function ($q) use ($exam_section_id) {
            if (!empty($q['id']) && $question = Question::find($q['id'])) {
                // Nếu có ID, cập nhật câu hỏi
                $question->update([
                    'exam_section_id' => $exam_section_id,
                    'image_url'       => $q['image_url'] ?? null,
                    'question_number'       => $q['question_number'] ?? null,
                    'audio_url'       => $q['audio_url'] ?? null,
                    'part_number'     => $q['part_number'],
                    'question_text'   => $q['question_text'] ?? null,
                    'option_a'        => $q['option_a'] ?? null,
                    'option_b'        => $q['option_b'] ?? null,
                    'option_c'        => $q['option_c'] ?? null,
                    'option_d'        => $q['option_d'] ?? null,
                    'correct_answer'  => $q['correct_answer'],
                    'updated_at'      => now(),
                ]);
            } else {
                // Nếu không có ID, tạo mới
                Question::create([
                    'exam_section_id' => $exam_section_id,
                    'image_url'       => $q['image_url'] ?? null,
                    'audio_url'       => $q['audio_url'] ?? null,
                    'question_number'       => $q['question_number'] ?? null,
                    'part_number'     => $q['part_number'],
                    'question_text'   => $q['question_text'] ?? null,
                    'option_a'        => $q['option_a'] ?? null,
                    'option_b'        => $q['option_b'] ?? null,
                    'option_c'        => $q['option_c'] ?? null,
                    'option_d'        => $q['option_d'] ?? null,
                    'correct_answer'  => $q['correct_answer'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        });

        // Trả về response JSON
        return response()->json([
            'message' => 'Xóa & cập nhật câu hỏi thành công',
            'code'    => 200,
            'data'    => $examSection->only([
                'id', 'exam_code', 'exam_name', 'section_name', 'part_number',
                'question_count', 'year', 'duration', 'max_score', 'is_deleted'
            ]),
            // 'deleted' => $deleteIds,
            // 'updated_or_created' => $questionsToUpdate->pluck('id')->toArray(),
            'meta'    => null
        ], 200);

    }

    


    
}



