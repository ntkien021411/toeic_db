<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;


class ExcelController extends Controller
{
    public function importExcel(Request $request)
    {
        // Kiểm tra xem file có tồn tại không
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');

        // Kiểm tra định dạng file (chỉ chấp nhận .xlsx)
        if ($file->getClientOriginalExtension() !== 'xlsx') {
            return response()->json(['error' => 'Invalid file format. Only .xlsx is allowed'], 400);
        }

        // Đọc file Excel
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();

        // Sử dụng iterator để đọc nhanh từng hàng
        $data = [];
        foreach ($worksheet->getRowIterator(2) as $rowIndex => $row) { // Bắt đầu từ hàng số 2
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Lấy tất cả cell, kể cả cell trống

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // Kiểm tra nếu hàng không đủ cột
            if (count($rowData) < 8) {
                continue;
            }

            $data[] = [
                'question_number' => $rowData[0],
                'question_text' => $rowData[1],
                'option_a' => $rowData[2],
                'option_b' => $rowData[3],
                'option_c' => $rowData[4],
                'option_d' => $rowData[5],
                'correct_answer' => $rowData[6],
                'explain' => $rowData[7],
            ];
        }

        return response()->json($data);
    }
}
