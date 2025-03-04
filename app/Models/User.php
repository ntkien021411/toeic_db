<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class User extends Model
{
    use HasFactory;
    
    protected $table = 'User'; // Chỉ định bảng tương ứng trong database

    protected $fillable = [
      'id', 'account_id', 'role', 'first_name', 'last_name', 'full_name','birth_date',
        'gender', 'phone', 'image_link', 'facebook_link','address', 'is_deleted', 'created_at', 'deleted_at'
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
    protected $hidden = [
        'updated_at', // Ẩn mật khẩu khi query
        'created_at'
    ];

     // Thiết lập quan hệ với Account
     public function account()
     {
         return $this->belongsTo(Account::class, 'account_id');
     }

     public function diplomas()
    {
        return $this->hasMany(Diploma::class, 'user_id');
    }

    // Một giáo viên có thể dạy nhiều lớp
    public function classes()
    {
        return $this->hasMany(Classes::class, 'teacher_id'); 
    }

}
