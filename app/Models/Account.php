<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Account extends Authenticatable
{
    use HasFactory;

    protected $table = 'Account'; // Đặt tên bảng theo đúng database

    protected $fillable = [
        'username',
        'email',
        'password',
        'active_status',
        'active_date',
        'is_first',
        'created_at',
        'deleted_at',
        'is_deleted'
    ];

    protected $hidden = [
        'password', // Ẩn mật khẩu khi query
        'updated_at', // Ẩn mật khẩu khi query
        'created_at'
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
    // Thêm quan hệ với User
    public function user()
    {
        return $this->hasOne(User::class, 'account_id');
    }
    public function tokens()
    {
        return $this->hasMany(Token::class, 'account_id');
    }
    
}
