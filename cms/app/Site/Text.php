<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** UTF-8 safe string helpers that work without the mbstring extension, and locale formatting. */
final class Text
{
    /** The first $length characters of $value. */
    public static function limit(string $value, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        if (strlen($value) <= $length) {
            return $value;
        }

        return preg_match('/^.{0,' . $length . '}/su', $value, $match) === 1 ? $match[0] : substr($value, 0, $length);
    }

    /** Collapse whitespace and cut to $words words, adding an ellipsis when shortened. */
    public static function words(string $value, int $words): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($words <= 0 || $value === '') {
            return $value;
        }

        $parts = explode(' ', $value);

        return count($parts) > $words ? implode(' ', array_slice($parts, 0, $words)) . '…' : $value;
    }

    /** Plain-text summary suitable for meta descriptions. */
    public static function summary(string $value, int $length = 160): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (strlen($value) <= $length) {
            return $value;
        }

        $cut = self::limit($value, $length);
        $space = strrpos($cut, ' ');

        return rtrim($space !== false && $space > $length / 2 ? substr($cut, 0, $space) : $cut, " ,.;:") . '…';
    }

    /** A number in the conventions of $locale, keeping its decimals. */
    public static function number(mixed $value, string $locale): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        $string = (string) $value;
        $decimals = str_contains($string, '.') ? strlen(rtrim(substr($string, strpos($string, '.') + 1), '0')) : 0;

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

            return (string) $formatter->format((float) $value);
        }

        return number_format((float) $value, $decimals);
    }

    /** A date in the conventions of $locale; $format is date, long, datetime or time. */
    public static function date(string $value, string $format, string $locale): string
    {
        $time = strtotime($value);

        if ($time === false) {
            return $value;
        }

        if (class_exists(\IntlDateFormatter::class)) {
            [$date, $clock] = match ($format) {
                'long' => [\IntlDateFormatter::LONG, \IntlDateFormatter::NONE],
                'datetime' => [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT],
                'time' => [\IntlDateFormatter::NONE, \IntlDateFormatter::SHORT],
                default => [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE],
            };
            $formatter = new \IntlDateFormatter($locale, $date, $clock, date_default_timezone_get());
            $formatted = $formatter->format($time);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return date(match ($format) {
            'long' => 'F j, Y',
            'datetime' => 'Y-m-d H:i',
            'time' => 'H:i',
            default => 'Y-m-d',
        }, $time);
    }
}
