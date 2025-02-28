<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSection extends Model
{
    use HasFactory;


    protected $table = 'Exam_Section';

    protected $fillable = [
        'exam_code', 
        'exam_name', 
        'section_name', 
        'part_number',
        'question_count',
        'year',
        'duration',
        'max_score',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_deleted'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_deleted' => 'boolean'
    ];

    public function examResults()
    {
        return $this->hasMany(ExamResult::class, 'exam_section_id');
    }
}
