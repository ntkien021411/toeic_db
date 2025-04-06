<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAnswer extends Model
{
    use HasFactory;

    // Specify the table associated with the model
    protected $table = 'Exam_Answers';

    // Specify the primary key if it's not 'id'
    protected $primaryKey = 'id';

    // Allow mass assignment for these fields
    protected $fillable = [
        'user_id',
        'exam_section_id',
        'question_number',
        'answer',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_deleted',
    ];

    // Define relationships if needed
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examSection()
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }
}
