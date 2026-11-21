<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reads the common query-string parameters of admin lists (search, sort,
 * rows per page, date ranges) safely, so every list behaves the same way.
 */
class AdminList
{
    public const PER_PAGE = [10, 25, 50, 100];

    public static function search(Request $request, int $max = 100): string
    {
        return trim(mb_substr((string) $request->query('search', ''), 0, $max));
    }

    public static function perPage(Request $request, int $default = 25): int
    {
        $n = (int) $request->query('per_page', $default);

        return in_array($n, self::PER_PAGE, true) ? $n : $default;
    }

    /** One of the allowed sort keys, or the default (the first key). */
    public static function sort(Request $request, array $allowed): string
    {
        $sort = (string) $request->query('sort', '');

        return array_key_exists($sort, $allowed) ? $sort : array_key_first($allowed);
    }

    /** A value from a fixed list (keys of $options), or ''. */
    public static function choice(Request $request, string $key, array $options): string
    {
        $value = (string) $request->query($key, '');

        return array_key_exists($value, $options) ? $value : '';
    }

    /** A YYYY-MM-DD date, or ''. */
    public static function date(Request $request, string $key): string
    {
        $value = (string) $request->query($key, '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : '';
    }

    /**
     * Whole days typed in the store's timezone, as a UTC [start, end] range for
     * created_at columns. Either end can be open (null).
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function dayRange(string $from, string $to): array
    {
        $tz = BusinessSetting::current()->timezone ?: config('app.timezone');
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            $from ? Carbon::parse($from, $tz)->startOfDay()->utc() : null,
            $to ? Carbon::parse($to, $tz)->endOfDay()->utc() : null,
        ];
    }

    /** Applies a day range to a created_at-style column. */
    public static function whereDays($query, string $column, string $from, string $to)
    {
        [$start, $end] = self::dayRange($from, $to);

        return $query->when($start, fn ($q) => $q->where($column, '>=', $start))
            ->when($end, fn ($q) => $q->where($column, '<=', $end));
    }

    /** A date in the store's timezone for display and exports. */
    public static function localDate($value, string $format = 'Y-m-d H:i'): string
    {
        if (! $value) {
            return '';
        }

        return Carbon::parse($value)->setTimezone(BusinessSetting::current()->timezone ?: config('app.timezone'))->format($format);
    }
}
