<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarketplacePaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'string', 'max:32'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_no' => ['required', 'string', 'max:80'],
            'account_name' => ['required', 'string', 'max:180'],
            'qr_template' => ['required', 'in:compact,compact2,qr_only,print'],
            'transfer_prefix' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'transfer_prefix.regex' => 'Tiền tố nội dung chỉ gồm chữ in hoa, số, dấu gạch ngang hoặc gạch dưới.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_id' => strtoupper(trim((string) $this->input('bank_id'))),
            'account_no' => preg_replace('/\s+/', '', (string) $this->input('account_no')),
            'account_name' => trim((string) $this->input('account_name')),
            'bank_name' => trim((string) $this->input('bank_name')),
            'transfer_prefix' => strtoupper(trim((string) $this->input('transfer_prefix'))),
        ]);
    }
}
