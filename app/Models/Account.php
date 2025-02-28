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

    public $timestamps = false; // Vì `created_at` đã có trong database

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
