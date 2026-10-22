<?php

declare(strict_types=1);

namespace CometCMS\Localization;

/** Visitor interface text; authored website content remains resource-owned. */
final class Messages
{
    private const TEXT = [
        'en' => [
            'yes' => 'Yes', 'no' => 'No', 'pagination' => 'Pagination', 'skip' => 'Skip to content', 'home' => 'Home', 'back_home' => 'Back to the homepage',
            'language' => 'Language', 'menu' => 'Menu', 'close' => 'Close', 'dark_mode' => 'Dark mode',
            'search' => 'Search', 'search_placeholder' => 'Search…', 'search_results_label' => 'Search results', 'search_results' => '{count} results', 'no_results' => 'No results found',
            'enlarge' => 'Enlarge image', 'gallery' => 'Image gallery', 'previous_image' => 'Previous image', 'next_image' => 'Next image',
            'form_success' => 'Thank you! Your message has been sent.', 'form_error' => 'Your message could not be sent. Please check your input and try again later.', 'send' => 'Send',
            'upload_limit' => 'Maximum {limit} MiB per file and in total per form.',
            'not_found_title' => 'Page not found', 'not_found_message' => 'The page you are looking for does not exist or has moved.',
        ],
        'de' => [
            'yes' => 'Ja', 'no' => 'Nein', 'pagination' => 'Seitennavigation', 'skip' => 'Zum Inhalt springen', 'home' => 'Startseite', 'back_home' => 'Zur Startseite',
            'language' => 'Sprache', 'menu' => 'Menü', 'close' => 'Schließen', 'dark_mode' => 'Dunkler Modus',
            'search' => 'Suche', 'search_placeholder' => 'Suchen…', 'search_results_label' => 'Suchergebnisse', 'search_results' => '{count} Ergebnisse', 'no_results' => 'Keine Treffer gefunden',
            'enlarge' => 'Bild vergrößern', 'gallery' => 'Bildergalerie', 'previous_image' => 'Vorheriges Bild', 'next_image' => 'Nächstes Bild',
            'form_success' => 'Vielen Dank! Ihre Nachricht wurde gesendet.', 'form_error' => 'Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.', 'send' => 'Senden',
            'upload_limit' => 'Maximal {limit} MiB pro Datei und insgesamt pro Formular.',
            'not_found_title' => 'Seite nicht gefunden', 'not_found_message' => 'Die gesuchte Seite existiert nicht oder wurde verschoben.',
        ],
    ];

    public static function get(string $key, string $locale, array $variables = []): string
    {
        $language = explode('-', $locale)[0];
        return strtr(self::TEXT[$language][$key] ?? self::TEXT['en'][$key] ?? $key, $variables);
    }
}
