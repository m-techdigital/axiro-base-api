<?php

namespace App\Http\Controllers;

use App\Services\MediaUploadService;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    public function update(Request $request)
    {
        $customer = auth('customer_api')->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:customers,phone,'.$customer->id],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
        ]);
        $customer->update($data);
        return success_response($customer->fresh(), 'Đã cập nhật thông tin cá nhân.');
    }

    public function updateAvatar(Request $request, MediaUploadService $service)
    {
        $data = $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'avatar.required' => 'Vui lòng chọn ảnh đại diện.',
            'avatar.image' => 'Tệp đã chọn phải là ảnh hợp lệ.',
            'avatar.mimes' => 'Ảnh đại diện chỉ hỗ trợ JPG, PNG hoặc WEBP.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 5 MB.',
        ]);
        $customer = auth('customer_api')->user();
        $uploaded = $service->storeMany([$data['avatar']], 'marketplace/customer-avatars')[0];
        $customer->update(['avatar_url' => $uploaded['url']]);
        return success_response($customer->fresh(), 'Đã cập nhật ảnh đại diện.');
    }
}
