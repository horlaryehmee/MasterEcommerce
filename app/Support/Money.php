<?php

namespace App\Support;

use App\Models\BusinessSetting;

/** Formats prices using the currency settings configured in the admin. */
class Money
{
    public static function format(int|float|string|null $amount, ?BusinessSetting $settings = null): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $s = $settings ?? BusinessSetting::current();
        $number = number_format(
            (float) $amount,
            max(0, min(4, (int) $s->decimal_places)),
            $s->decimal_separator ?? '.',
            $s->thousands_separator ?? ''
        );

        $symbol = (string) $s->currency_symbol;
        if ($symbol === '') {
            return trim($number.' '.$s->currency_code);
        }

        // Single-character symbols attach directly ("₦5,000"); longer ones get a space ("KSh 5,000").
        $space = mb_strlen($symbol) > 1 ? ' ' : '';

        return $s->currency_position === 'after'
            ? $number.' '.$symbol
            : $symbol.$space.$number;
    }
}
