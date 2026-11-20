<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A button/announcement link: a site path ("/products"), an anchor ("#about"),
 * a full http(s) URL, or the keyword "whatsapp" (opens the business chat).
 * Blocks javascript:, data: and other unsafe schemes.
 */
class SafeLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $value = (string) $value;
        $ok = $value === 'whatsapp'
            || preg_match('#^/(?!/)[^\s]*$#', $value)
            || preg_match('/^#[\w-]*$/', $value)
            || (preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL));

        if (! $ok) {
            $fail('The :attribute must be a page path like /products, a full https:// link, or "whatsapp".');
        }
    }
}
