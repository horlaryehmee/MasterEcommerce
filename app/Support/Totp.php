<?php

namespace App\Support;

/**
 * Time-based one-time passwords (RFC 6238), the 6-digit codes shown by
 * Google Authenticator, Microsoft Authenticator, Authy, 1Password, etc.
 */
class Totp
{
    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    /** A new random secret (160 bits, Base32), as authenticator apps expect. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * The time step the code matches (within ±$window steps for clock drift),
     * or null. Callers store the step to stop the same code being used twice.
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $key = self::base32Decode($secret);
        $step = intdiv($time ?? time(), self::PERIOD);
        $match = null;
        // Check every step in the window (no early exit) so timing doesn't reveal which matched.
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($key, $step + $i), $code)) {
                $match ??= $step + $i;
            }
        }

        return $match;
    }

    /** The current code (for tests). */
    public static function now(string $secret, ?int $time = null): string
    {
        return self::code(self::base32Decode($secret), intdiv($time ?? time(), self::PERIOD));
    }

    /** otpauth:// link for the QR code. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    protected static function code(string $key, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=]+/', '', $text));
        $bits = '';
        foreach (str_split($text) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                return '';
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
