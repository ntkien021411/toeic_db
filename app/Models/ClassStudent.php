<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassStudent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'class';

    protected $fillable = [
        'class_code', 'class_name', 'start_date', 'end_date',
        'student_count', 'is_full', 'teacher_id', 'is_deleted'
    ];

    protected $casts = [
        'is_full' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->hasMany(ClassUser::class, 'class_id');
    }
}
