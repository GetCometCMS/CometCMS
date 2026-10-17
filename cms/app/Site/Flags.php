<?php

declare(strict_types=1);

namespace CometCMS\Site;

/**
 * Flags for website languages, as shown by the language switcher.
 *
 * A language is not a country, so this is a convention: the same mapping the
 * admin uses for its own flags (web/src/composables/localeOptions.js), and the
 * region of a regional code (de-at → Austria) when there is a flag for it.
 * The images come from flag-icons 7.5.0 (MIT, see assets/flags/LICENSE).
 */
final class Flags
{
    private const LANGUAGE_COUNTRIES = [
        'af' => 'za', 'sq' => 'al', 'ar' => 'sa', 'hy' => 'am', 'az' => 'az', 'eu' => 'es', 'be' => 'by',
        'bs' => 'ba', 'bg' => 'bg', 'ca' => 'es', 'zh' => 'cn', 'zh-tw' => 'tw', 'hr' => 'hr', 'cs' => 'cz',
        'da' => 'dk', 'nl' => 'nl', 'en' => 'gb', 'et' => 'ee', 'fi' => 'fi', 'fr' => 'fr', 'gl' => 'es',
        'ka' => 'ge', 'de' => 'de', 'el' => 'gr', 'he' => 'il', 'hi' => 'in', 'hu' => 'hu', 'is' => 'is',
        'id' => 'id', 'ga' => 'ie', 'it' => 'it', 'ja' => 'jp', 'kk' => 'kz', 'ko' => 'kr', 'lv' => 'lv',
        'lt' => 'lt', 'mk' => 'mk', 'ms' => 'my', 'mt' => 'mt', 'no' => 'no', 'fa' => 'ir', 'pl' => 'pl',
        'pt' => 'pt', 'pt-br' => 'br', 'ro' => 'ro', 'ru' => 'ru', 'sr' => 'rs', 'sk' => 'sk', 'sl' => 'si',
        'es' => 'es', 'sw' => 'tz', 'sv' => 'se', 'tl' => 'ph', 'th' => 'th', 'tr' => 'tr', 'uk' => 'ua',
        'ur' => 'pk', 'uz' => 'uz', 'vi' => 'vn', 'cy' => 'gb-wls',
    ];

    /** The flag's country code for a language, or '' when there is none. */
    public static function country(string $locale): string
    {
        $locale = strtolower($locale);
        if (isset(self::LANGUAGE_COUNTRIES[$locale])) {
            return self::LANGUAGE_COUNTRIES[$locale];
        }

        $parts = explode('-', $locale);
        if (count($parts) > 1 && self::file(end($parts)) !== null) {
            return end($parts);
        }

        return self::LANGUAGE_COUNTRIES[$parts[0]] ?? '';
    }

    /** Path of a country's flag image, or null for an unknown country. */
    public static function file(string $country): ?string
    {
        if (preg_match('/^[a-z]{2}(-[a-z]{3})?$/', $country) !== 1) {
            return null;
        }
        $path = __DIR__ . '/assets/flags/' . $country . '.svg';

        return is_file($path) ? $path : null;
    }
}
