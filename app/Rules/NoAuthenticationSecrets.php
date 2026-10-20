<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * MDM 12 Decision 12-24 — tickets must not contain authentication secrets.
 */
class NoAuthenticationSecrets implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $patterns = [
            '/\bpassword\s*[:=]/i',
            '/\bpasswd\s*[:=]/i',
            '/\botp\s*[:=]/i',
            '/\bone[-\s]?time\s+pass/i',
            '/\brecovery\s+code/i',
            '/\bapi[_\s-]?key\s*[:=]/i',
            '/\bbearer\s+[a-z0-9\-._~+\/]+=*/i',
            '/\btoken\s*[:=]\s*\S+/i',
            '/\bsecret\s*[:=]/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                $fail('Do not include passwords, OTP codes, recovery codes, API keys, tokens, or similar secrets.');

                return;
            }
        }
    }
}
