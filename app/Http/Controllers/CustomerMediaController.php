<?php

namespace App\Http\Controllers;

use App\Services\MediaUploadService;
use Illuminate\Http\Request;

class CustomerMediaController extends Controller
{
    public function store(Request $request, MediaUploadService $service)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], [
            'images.required' => 'Vui lòng chọn ít nhất một ảnh.',
            'images.max' => 'Mỗi lần chỉ được tải lên tối đa 8 ảnh.',
            'images.*.image' => 'Tệp đã chọn phải là ảnh hợp lệ.',
            'images.*.mimes' => 'Ảnh chỉ hỗ trợ định dạng JPG, PNG, WEBP hoặc GIF.',
            'images.*.max' => 'Mỗi ảnh không được vượt quá 5 MB.',
        ]);

        return success_response(
            $service->storeMany($data['images']),
            'Đã tải ảnh lên.',
            201
        );
    }
}
