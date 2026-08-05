<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EscrowBoxPublicText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $patterns = [
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
            '/\b(?:https?:\/\/|www\.)\S+/i',
            '/(?:\+?84|0)(?:[\s.\-]?\d){8,10}/',
            '/\b(?:zalo|telegram|facebook|messenger|discord|whatsapp|wechat)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $fail('Nội dung không được chứa email, số điện thoại, liên kết hoặc kênh liên hệ trực tiếp.');

                return;
            }
        }
    }
}
