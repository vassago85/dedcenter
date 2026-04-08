<?php

namespace App\Support;

/**
 * Recovers whole numbers from Excel/Google Sheets paste (e.g. 8.43E+08, scientific IDs/phones).
 */
final class SpreadsheetNumberNormalizer
{
    /**
     * Expand scientific notation to an integer digit string (no separators).
     */
    public static function expandScientificToIntegerString(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (! preg_match('/^([+-]?\d+(?:\.\d+)?)[eE]([+-]?\d+)$/', $value, $m)) {
            return $value;
        }

        $mantissa = $m[1];
        $exp = (int) $m[2];

        if (function_exists('bcpow') && function_exists('bcmul') && function_exists('bcdiv')) {
            $scale = 30;
            if ($exp >= 0) {
                $pow = bcpow('10', (string) $exp, 0);
                $out = bcmul($mantissa, $pow, $scale);
            } else {
                $pow = bcpow('10', (string) abs($exp), 0);
                $out = bcdiv($mantissa, $pow, $scale);
            }
            $out = rtrim(rtrim($out, '0'), '.');
            if (str_contains($out, '.')) {
                $out = explode('.', $out, 2)[0];
            }

            return $out;
        }

        return (string) (int) round((float) $value);
    }

    /**
     * SA ID / numeric cell: digits only after fixing E-notation.
     */
    public static function normalizeIdDigits(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        if (str_contains($t, 'e') || str_contains($t, 'E')) {
            $t = self::expandScientificToIntegerString($t);
        }
        $digits = preg_replace('/\D+/', '', $t);

        return ($digits === '' || $digits === null) ? null : $digits;
    }

    /**
     * Phone: digits only (strips spaces, +27, etc.) after fixing E-notation.
     */
    public static function normalizePhoneDigits(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        if (str_contains($t, 'e') || str_contains($t, 'E')) {
            $t = self::expandScientificToIntegerString($t);
        }
        $digits = preg_replace('/\D+/', '', $t);

        return ($digits === '' || $digits === null) ? null : $digits;
    }

    public static function truncateField(?string $value, int $max = 255): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }
}
