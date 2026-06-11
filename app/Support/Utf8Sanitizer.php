<?php

namespace App\Support;

class Utf8Sanitizer
{
    /**
     * Sanitizes nested data so it can be safely JSON encoded by Eloquent casts.
     */
    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitizedKey = is_string($key) ? self::sanitizeString($key) : $key;
                $sanitized[$sanitizedKey] = self::sanitize($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return self::sanitizeString($value);
        }

        return $value;
    }

    private static function sanitizeString(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return self::removeInvalidControls($value);
        }

        $encoding = mb_detect_encoding($value, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'Windows-1252';
        $converted = mb_convert_encoding($value, 'UTF-8', $encoding);

        if (! mb_check_encoding($converted, 'UTF-8')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $converted) ?: '';
        }

        return self::removeInvalidControls($converted);
    }

    private static function removeInvalidControls(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }
}
