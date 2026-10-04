<?php

/**
 * PHP built-in server router.
 * Mimics Apache's mod_rewrite behaviour from .htaccess:
 * - Serve the compiled admin UI assets (admin/…) directly.
 * - Everything else goes through index.php, which also serves media files.
 *
 * Only files inside admin/ are ever served statically. storage/, app/ and
 * config/ contain sessions, password hashes, tokens and backups, so they must
 * never be reachable as raw files.
 *
 * Usage:
 *   php -S localhost:8000 router.php
 */

$uri  = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$adminRoot = realpath(__DIR__ . '/admin');
$file = realpath(__DIR__ . $uri);

if (
    $adminRoot !== false
    && $file !== false
    && is_file($file)
    && str_starts_with($file, $adminRoot . DIRECTORY_SEPARATOR)
    && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
) {
    return false;
}

// php -S sets SCRIPT_NAME to the request URI path, but Http::path() and the
// session-cookie path calculation in bootstrap.php both derive the app's base
// prefix from dirname(SCRIPT_NAME). Force it to match what Apache+mod_rewrite
// would set (i.e. the actual entry-point file) so path stripping works correctly.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';

require __DIR__ . '/index.php';
