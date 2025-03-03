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

    public $timestamps = false; // Tự động cập nhật created_at và updated_at
    protected static function boot()
    {
        parent::boot();

        // Cập nhật created_at & updated_at khi insert
        static::creating(function ($model) {
            $model->created_at = now();
            $model->updated_at = now();
        });

        // Cập nhật updated_at khi update
        static::updating(function ($model) {
            $model->updated_at = now();
        });
    }

    public function examResults()
    {
        return $this->hasMany(ExamResult::class, 'exam_section_id');
    }
    // Quan hệ 1-n với Question
    public function questions()
    {
        return $this->hasMany(Question::class, 'exam_section_id', 'id')->where('is_deleted', false);
    }
}
