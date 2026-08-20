<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UiuEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim((string) $value));
        $domain = substr((string) strrchr($email, '@'), 1);

        if ($domain !== 'uiu.ac.bd' && ! str_ends_with($domain, '.uiu.ac.bd')) {
            $fail('Please use a valid UIU email address.');
        }
    }
}
