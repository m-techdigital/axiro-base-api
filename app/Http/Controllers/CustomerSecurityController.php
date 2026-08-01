<?php

namespace App\Http\Controllers;

use App\Services\Auth\CustomerSecurityService;
use App\Services\Auth\CustomerTwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerSecurityController extends Controller
{
    public function __construct(private CustomerSecurityService $service, private CustomerTwoFactorService $twoFactor) {}

    public function requestEmailChange(Request $request)
    {
        $customer = auth('customer_api')->user();
        $data = $request->validate(['email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)]]);
        $this->service->requestEmailChange($customer, $data['email']);

        return success_response(null, 'Đã gửi liên kết xác nhận đến địa chỉ thư điện tử mới.');
    }

    public function verifyEmailChange(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);

        return success_response($this->service->verifyEmailChange($data['token']), 'Đã xác nhận địa chỉ thư điện tử mới.');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'password' => 'required|string|min:8|confirmed']);
        $this->service->changePassword(auth('customer_api')->user(), $data['current_password'], $data['password']);

        return success_response(null, 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.');
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['login' => 'required|string|max:255']);
        $this->service->forgotPassword($data['login']);

        return success_response(null, 'Nếu tài khoản có địa chỉ thư điện tử hợp lệ, hệ thống đã gửi hướng dẫn đặt lại mật khẩu.');
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => 'required|string|min:8|confirmed']);
        $this->service->resetPassword($data['token'], $data['email'], $data['password']);

        return success_response(null, 'Đã đặt lại mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.');
    }

    public function twoFactorStatus()
    {
        $customer = auth('customer_api')->user();

        return success_response(['enabled' => $this->twoFactor->enabled($customer), 'confirmed_at' => $customer->two_factor_confirmed_at]);
    }

    public function beginTwoFactorSetup()
    {
        return success_response($this->twoFactor->beginSetup(auth('customer_api')->user()), 'Đã tạo khóa xác thực hai lớp.');
    }

    public function confirmTwoFactor(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:20']);

        return success_response($this->twoFactor->confirm(auth('customer_api')->user(), $data['code']), 'Đã bật xác thực hai lớp.');
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:20']);

        return success_response($this->twoFactor->regenerateRecoveryCodes(auth('customer_api')->user(), $data['code']), 'Đã tạo lại mã khôi phục.');
    }

    public function disableTwoFactor(Request $request)
    {
        $data = $request->validate(['password' => 'required|string', 'code' => 'required|string|max:20']);
        $this->twoFactor->disable(auth('customer_api')->user(), $data['password'], $data['code']);

        return success_response(null,'Đã tắt xác thực hai lớp.');
    }
}
