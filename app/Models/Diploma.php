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
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
