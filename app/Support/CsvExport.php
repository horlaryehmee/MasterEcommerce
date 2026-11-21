<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a list as a CSV download that opens cleanly in Excel, Google Sheets
 * and Numbers (UTF-8 with a BOM, so ₦ and accented names show correctly).
 */
class CsvExport
{
    /**
     * @param  string  $name  e.g. "orders" -> "my-shop-orders-2026-11-21.csv"
     * @param  array<int, string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $name, array $headings, iterable $rows): StreamedResponse
    {
        $shop = Str::slug(BusinessSetting::current()->business_name ?: config('app.name')) ?: 'shop';
        $filename = "{$shop}-{$name}-".now(BusinessSetting::current()->timezone ?: config('app.timezone'))->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Spreadsheet apps run cells starting with = + - @ as formulas, so text
     * like that is prefixed with an apostrophe. Numbers are left alone.
     */
    public static function cell(mixed $value): string|int|float
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    /** Money as a plain number (2 decimals) so spreadsheets can add it up. */
    public static function amount($value): float
    {
        return round((float) $value, 2);
    }
}
