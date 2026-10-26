<?php

declare(strict_types=1);

namespace CometCMS\Localization;

/** Visitor interface text; authored website content remains resource-owned. */
final class Messages
{
    private const TEXT = [
        'en' => [
            'yes' => 'Yes', 'no' => 'No', 'pagination' => 'Pagination', 'skip' => 'Skip to content', 'home' => 'Home', 'back_home' => 'Back to the homepage',
            'modal' => 'Modal', 'modal_title_sample' => 'Link text', 'site_content_placeholder' => 'Linked page: the page a modal link opens appears here.',
            'language' => 'Language', 'menu' => 'Menu', 'close' => 'Close', 'dark_mode' => 'Dark mode',
            'search' => 'Search', 'search_placeholder' => 'Search…', 'search_results_label' => 'Search results', 'search_results_title' => 'Search results', 'search_results' => '{count} results', 'no_results' => 'No results found',
            'filters' => 'Filters', 'filter_all' => 'All', 'filter_apply' => 'Apply', 'filter_reset' => 'Reset filters', 'filter_from' => 'From', 'filter_to' => 'To', 'filters_active' => '{count} filters active',
            'sort' => 'Sort by', 'sort_default' => 'Default', 'order_az' => 'A–Z', 'order_za' => 'Z–A', 'order_new' => 'newest first', 'order_old' => 'oldest first', 'order_low' => 'lowest first', 'order_high' => 'highest first', 'order_yes_first' => 'yes first', 'order_no_first' => 'no first',
            'field_title' => 'Title', 'field_published_at' => 'Date', 'field_created_at' => 'Created', 'field_updated_at' => 'Updated',
            'enlarge' => 'Enlarge image', 'gallery' => 'Image gallery', 'previous_image' => 'Previous image', 'next_image' => 'Next image',
            'form_success' => 'Thank you! Your message has been sent.', 'form_error' => 'Your message could not be sent. Please check your input and try again later.', 'send' => 'Send',
            'upload_limit' => 'Maximum {limit} MiB per file and in total per form.',
            'captcha_required' => 'Complete the spam check before sending.',
            'captcha_checking' => 'Checking spam protection…',
            'captcha_unavailable' => 'Spam protection is unavailable. Please reload the page and try again later.',
            'captcha_javascript' => 'Enable JavaScript to complete the spam check and send this form.',
            'captcha_preview' => 'Spam verification appears on the live website.',
            'not_found_title' => 'Page not found', 'not_found_message' => 'The page you are looking for does not exist or has moved.',
            'maintenance_title' => 'Maintenance',
        ],
        'de' => [
            'yes' => 'Ja', 'no' => 'Nein', 'pagination' => 'Seitennavigation', 'skip' => 'Zum Inhalt springen', 'home' => 'Startseite', 'back_home' => 'Zur Startseite',
            'modal' => 'Modal', 'modal_title_sample' => 'Linktext', 'site_content_placeholder' => 'Verlinkte Seite: Hier erscheint die Seite, die ein Modal-Link öffnet.',
            'language' => 'Sprache', 'menu' => 'Menü', 'close' => 'Schließen', 'dark_mode' => 'Dunkler Modus',
            'search' => 'Suche', 'search_placeholder' => 'Suchen…', 'search_results_label' => 'Suchergebnisse', 'search_results_title' => 'Suchergebnisse', 'search_results' => '{count} Ergebnisse', 'no_results' => 'Keine Treffer gefunden',
            'filters' => 'Filter', 'filter_all' => 'Alle', 'filter_apply' => 'Anwenden', 'filter_reset' => 'Filter zurücksetzen', 'filter_from' => 'Von', 'filter_to' => 'Bis', 'filters_active' => '{count} Filter aktiv',
            'sort' => 'Sortieren nach', 'sort_default' => 'Standard', 'order_az' => 'A–Z', 'order_za' => 'Z–A', 'order_new' => 'neueste zuerst', 'order_old' => 'älteste zuerst', 'order_low' => 'niedrigste zuerst', 'order_high' => 'höchste zuerst', 'order_yes_first' => 'ja zuerst', 'order_no_first' => 'nein zuerst',
            'field_title' => 'Titel', 'field_published_at' => 'Datum', 'field_created_at' => 'Erstellt', 'field_updated_at' => 'Aktualisiert',
            'enlarge' => 'Bild vergrößern', 'gallery' => 'Bildergalerie', 'previous_image' => 'Vorheriges Bild', 'next_image' => 'Nächstes Bild',
            'form_success' => 'Vielen Dank! Ihre Nachricht wurde gesendet.', 'form_error' => 'Ihre Nachricht konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.', 'send' => 'Senden',
            'upload_limit' => 'Maximal {limit} MiB pro Datei und insgesamt pro Formular.',
            'captcha_required' => 'Bitte schließen Sie vor dem Senden die Spam-Prüfung ab.',
            'captcha_checking' => 'Spam-Schutz wird geprüft…',
            'captcha_unavailable' => 'Der Spam-Schutz ist nicht verfügbar. Bitte laden Sie die Seite neu und versuchen Sie es später erneut.',
            'captcha_javascript' => 'Aktivieren Sie JavaScript, um die Spam-Prüfung abzuschließen und dieses Formular zu senden.',
            'captcha_preview' => 'Die Spam-Prüfung erscheint auf der veröffentlichten Website.',
            'not_found_title' => 'Seite nicht gefunden', 'not_found_message' => 'Die gesuchte Seite existiert nicht oder wurde verschoben.',
            'maintenance_title' => 'Wartungsarbeiten',
        ],
    ];

    public static function get(string $key, string $locale, array $variables = []): string
    {
        $language = explode('-', $locale)[0];
        return strtr(self::TEXT[$language][$key] ?? self::TEXT['en'][$key] ?? $key, $variables);
    }
}
