<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    
    protected $except = [
        'api/*', // Bỏ qua CSRF(lỗi 419) cho tất cả API
        'upload'
    ];
}
