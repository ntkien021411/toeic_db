<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classes extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'Room';

    protected $fillable = [
        'class_code', 'class_name', 'class_type','start_date', 'end_date',
        'start_time', 'end_time', 'days',
        'student_count', 'is_full', 'teacher_id', 'is_deleted'
    ];

    protected $casts = [
        'is_full' => 'boolean',
        'is_deleted' => 'boolean',
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
    
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->hasMany(ClassUser::class, 'class_id');
    }
}
