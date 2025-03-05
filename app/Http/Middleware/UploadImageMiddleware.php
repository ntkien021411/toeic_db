<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UploadImageMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $uploadedUrls = [];

        // Upload ảnh nếu có
        if ($request->hasFile('image')) {
            $uploadedUrls['image'] = Cloudinary::upload($request->file('image')->getRealPath())->getSecurePath();
        }

        // Upload audio nếu có
        if ($request->hasFile('audio')) {
            $uploadedUrls['audio'] = Cloudinary::upload($request->file('audio')->getRealPath(), [
                'resource_type' => 'video' // Cloudinary xử lý audio như video
            ])->getSecurePath();
        }

        // Gán URL vào request để các middleware/controller khác có thể sử dụng
        $request->merge(['uploaded_urls' => $uploadedUrls]);

        return $next($request);
    }
}
