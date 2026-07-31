<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\LoginCustomerRequest;
use App\Http\Requests\Customer\RegisterCustomerRequest;
use App\Http\Requests\Customer\VerifyTwoFactorRequest;
use App\Services\Auth\CustomerAuthService;
use App\Support\RefreshTokenCookie;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(private CustomerAuthService $service) {}

    public function register(RegisterCustomerRequest $request)
    {
        return $this->respond($this->service->register($request->validated()));
    }

    public function login(LoginCustomerRequest $request)
    {
        $validated = $request->validated();
        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';
        $data = $this->service->login([
            $field => $validated['login'],
            'password' => $validated['password'],
        ]);

        if (! $data) {
            return error_response('Tên đăng nhập hoặc mật khẩu không đúng.', null, 401);
        }

        if (! empty($data['two_factor_required'])) {
            return success_response($data, 'Vui lòng nhập mã xác thực hai lớp.');
        }

        return $this->respond($data);
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request)
    {
        $data = $request->validated();
        $result = $this->service->completeTwoFactor(
            $data['challenge_token'],
            $data['code'],
        );

        if (! $result) {
            return error_response(
                'Mã xác thực không đúng, đã dùng hoặc đã hết hạn.',
                null,
                422,
            );
        }

        return $this->respond($result);
    }

    public function refresh(Request $request)
    {
        $token = (string) $request->cookie(
            config('auth.customer_refresh_cookie.name'),
        );

        if ($token === '') {
            return error_response(
                'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                null,
                401,
            );
        }

        $data = $this->service->refresh($token);

        if (! $data) {
            return error_response(
                'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                null,
                401,
            )->withoutCookie(RefreshTokenCookie::forget(
                config('auth.customer_refresh_cookie.name'),
                'auth.customer_refresh_cookie',
            ));
        }

        return $this->respond($data);
    }

    public function me()
    {
        $customer = auth('customer_api')->user()?->load('wallet');

        if (! $customer) {
            return success_response(null);
        }

        $data = $customer->only([
            'id',
            'code',
            'username',
            'name',
            'email',
            'phone',
            'status',
            'avatar_url',
            'last_login_at',
            'email_verified_at',
        ]);
        $data['balance'] = $customer->wallet?->available_balance ?? '0.00';
        $data['held_balance'] = $customer->wallet?->held_balance ?? '0.00';
        $data['two_factor_enabled'] = (bool) $customer->two_factor_confirmed_at;

        return success_response($data);
    }

    public function logout(Request $request)
    {
        $this->service->logout((string) $request->cookie(
            config('auth.customer_refresh_cookie.name'),
        ));

        return success_response(null, 'Đã đăng xuất.')
            ->withoutCookie(RefreshTokenCookie::forget(
                config('auth.customer_refresh_cookie.name'),
                'auth.customer_refresh_cookie',
            ));
    }

    private function respond(array $data)
    {
        $model = $data['customer']->load('wallet');
        $customer = $model->only([
            'id',
            'code',
            'username',
            'name',
            'email',
            'phone',
            'status',
            'avatar_url',
            'email_verified_at',
        ]);
        $customer['balance'] = $model->wallet?->available_balance ?? '0.00';
        $customer['held_balance'] = $model->wallet?->held_balance ?? '0.00';
        $customer['two_factor_enabled'] = (bool) $model->two_factor_confirmed_at;

        return success_response([
            'customer' => $customer,
            'access_token' => $data['access_token'],
        ])->withCookie(RefreshTokenCookie::make(
            config('auth.customer_refresh_cookie.name'),
            $data['refresh_token'],
            'auth.customer_refresh_cookie',
        ));
    }
}
