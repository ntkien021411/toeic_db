<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'class_user';

    protected $fillable = [
        'class_id', 'user_id', 'joined_at', 'left_at', 'is_deleted'
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
