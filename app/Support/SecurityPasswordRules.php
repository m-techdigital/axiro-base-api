<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class SecurityPasswordRules
{
    public static function make(): Password
    {
        $policy = config('security.password', []);
        $rule = Password::min((int) ($policy['min_length'] ?? 8));

        if ($policy['require_mixed_case'] ?? true) {
            $rule->mixedCase();
        }

        if ($policy['require_numbers'] ?? true) {
            $rule->numbers();
        }

        if ($policy['require_symbols'] ?? false) {
            $rule->symbols();
        }

        if ($policy['uncompromised'] ?? false) {
            $rule->uncompromised();
        }

        return $rule;
    }
}
