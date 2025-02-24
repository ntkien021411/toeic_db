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
        'is_active_date',
        'active_date',
        'is_first',
        'created_at',
        'deleted_at'
    ];

    protected $hidden = [
        'password', // Ẩn mật khẩu khi query
    ];

    public $timestamps = false; // Vì `created_at` đã có trong database

    public function tokens()
    {
        return $this->hasMany(Token::class, 'account_id');
    }
}
