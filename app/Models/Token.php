<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    use HasFactory;

    protected $table = 'Token'; // Chỉ định đúng tên bảng

    protected $fillable = [
        'account_id',
        'token',
        'expired_at',
        'refresh_token'
    ];

    public $timestamps = false; // Vì `created_at` đã có mặc định SQL

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
