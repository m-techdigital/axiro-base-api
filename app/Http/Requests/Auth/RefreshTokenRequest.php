<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['refresh_token' => ['nullable', 'string']];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->refresh_token && $this->cookie(config('auth.refresh_cookie.name'))) {
            $this->merge(['refresh_token' => $this->cookie(config('auth.refresh_cookie.name'))]);
        }
    }
}
