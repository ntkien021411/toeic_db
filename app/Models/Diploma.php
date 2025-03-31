<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diploma extends Model
{
    use HasFactory;

    protected $table = 'Diploma';

    protected $fillable = [
        'user_id', 'certificate_name', 'score', 'level',
        'issued_by', 'issue_date', 'expiry_date', 'certificate_image', 'is_deleted'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'score' => 'decimal:2', // Chuyển đổi score thành số thập phân với 2 chữ số thập phân
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
