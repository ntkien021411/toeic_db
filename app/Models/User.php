<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;
    
    protected $table = 'user'; // Chỉ định bảng tương ứng trong database

    protected $fillable = [
        'account_id', 'role', 'first_name', 'last_name', 'birth_date',
        'gender', 'phone', 'image_link', 'facebook_link', 'is_deleted', 'created_at', 'deleted_at'
    ];
    
    protected $hidden = [
        'updated_at', // Ẩn mật khẩu khi query
        'created_at'
    ];
}
