<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;


    protected $table = 'Question';

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

    public function examSection()
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
