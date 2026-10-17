<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** UTF-8 safe string helpers that work without the mbstring extension. */
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
}
