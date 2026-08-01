<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('customers')->ignore($customer?->id)],
            'username' => ['required', 'string', 'max:80', Rule::unique('customers')->ignore($customer?->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('customers')->ignore($customer?->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('customers')->ignore($customer?->id)],
            'password' => [$customer ? 'nullable' : 'required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return ['username' => 'tên đăng nhập', 'name' => 'tên khách hàng', 'email' => 'thư điện tử', 'phone' => 'số điện thoại', 'password' => 'mật khẩu', 'status' => 'trạng thái'];
    }
}
