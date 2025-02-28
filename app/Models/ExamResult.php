<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamResult extends Model
{
    use HasFactory;


    protected $table = 'Exam_Result';

    protected $fillable = [
        'user_id', 
        'exam_section_id', 
        'score', 
        'correct_answers', 
        'wrong_answers',
        'submitted_at',
        'updated_at',
        'deleted_at',
        'is_deleted'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_deleted' => 'boolean'
    ];

    public function examSection()
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
