<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ExamResult;
use App\Models\ExamSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class ExamResultController extends Controller
{
    //  Xem danh sách lịch sử bài thi của học viên 
    public function examHistory(Request $request, $user_id)
    {
          // Kiểm tra user_id có tồn tại không
        $user = User::find($user_id);
        if (!$user) {
            return response()->json([
                'message' => 'User không tồn tại',
                'code' => 404,
                'data' => null,
                'meta' => null
            ], 404);
        }

        // Kiểm tra role có phải STUDENT không
        if ($user->role !== 'STUDENT') {
            return response()->json([
                'message' => 'User không phải STUDENT',
                'code' => 403,
                'data' => null,
                'meta' => null
            ], 403);
        }

        // Lấy danh sách bài thi có phân trang
        $results = ExamResult::where('user_id', $user_id)
            ->orderBy('submitted_at', 'desc')
            ->paginate($request->input('per_page', 5));

        return response()->json([
            'message' => 'Lấy lịch sử bài thi thành công',
            'code' => 200,
            'data' => $results->items(),
            'meta' => $results->total() > 0 ?[
                    'total' => $results->total(),
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'last_page' => $results->lastPage(),
                    'next_page_url' => $results->nextPageUrl(),
                    'prev_page_url' => $results->previousPageUrl(),
                    'first_page_url' => $results->url(1),
                    'last_page_url' => $results->url($results->lastPage())
                ] : null
        ], 200);
    }
    // Tìm kiếm bài thi học viên đã làm
    public function searchExamResults(Request $request)
    {
        if ($request->filled('user_id')) {
            // Kiểm tra user_id có tồn tại không
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    'message' => 'User không tồn tại',
                    'code' => 404,
                    'data' => null,
                    'meta' => null
                ], 404);
            }

            // Kiểm tra role có phải STUDENT không
            if ($user->role !== 'STUDENT') {
                return response()->json([
                    'message' => 'User không phải STUDENT',
                    'code' => 403,
                    'data' => null,
                    'meta' => null
                ], 403);
            }
        }

        // Xây dựng truy vấn tìm kiếm
        $query = ExamResult::query();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('exam_section_id')) {
            $query->where('exam_section_id', $request->exam_section_id);
        }
        if ($request->filled('score_min')) {
            $query->where('score', '>=', $request->score_min);
        }
        if ($request->filled('score_max')) {
            $query->where('score', '<=', $request->score_max);
        }
        // Nếu client truyền correct_answers thì lọc theo điều kiện đó
        if ($request->filled('correct_answers')) {
            $query->where('correct_answers', '>=', $request->correct_answers);
        }

        // Nếu client truyền wrong_answers thì lọc theo điều kiện đó
        if ($request->filled('wrong_answers')) {
            $query->where('wrong_answers', '>=', $request->wrong_answers);
        }

        // Nếu client truyền submitted_at thì lọc theo ngày đó
        if ($request->filled('submitted_at')) {
            $query->whereDate('submitted_at', $request->submitted_at);
        }

        // Thực hiện phân trang
        $perPage = $request->query('per_page', 5);
        $results = $query->orderBy('submitted_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Tìm kiếm bài thi thành công',
            'code' => 200,
            'data' => $results->items(),
            'meta' => $results->total() > 0 ?[
                    'total' => $results->total(),
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'last_page' => $results->lastPage(),
                    'next_page_url' => $results->nextPageUrl(),
                    'prev_page_url' => $results->previousPageUrl(),
                    'first_page_url' => $results->url(1),
                    'last_page_url' => $results->url($results->lastPage())
                ] : null
        ], 200);
    }

    // e.6.3: Xem thông tin chi tiết bài thi
    public function examDetail($exam_id)
    {
        $exam = ExamSection::where('id', $exam_id)->where('is_deleted', false)->first();

        if (!$exam) {
            return response()->json([
                'message' => 'Bài thi không tồn tại',
                'code' => 404,
                'data' => null,
               'meta' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Thông tin bài thi  được lấy thành công.',
            'code' => 200,
            'data' => $exam,
            'meta' => null
        ], 200);
    }

}
