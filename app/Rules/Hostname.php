<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Hostname implements ValidationRule
{
    /**
     * RFC 1123 hostname pattern
     */
    public const PATTERN = '/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !preg_match(self::PATTERN, $value)) {
            $fail('The :attribute must be a valid RFC 1123 hostname.');
        }
    }
}
