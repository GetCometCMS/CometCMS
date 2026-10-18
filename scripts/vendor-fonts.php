<?php

/** Reproduce shipped fonts: php scripts/vendor-fonts.php (outbound HTTPS required).
 * --resolve is only used when deliberately refreshing the pinned package versions.
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/cms/app/Site';
require $root . '/FontFiles.php';
require $root . '/Fontsource.php';
use CometCMS\Site\FontFiles;
use CometCMS\Site\Fontsource;

$ids = ['inter', 'roboto', 'open-sans', 'lato', 'montserrat', 'nunito-sans', 'source-sans-3', 'merriweather', 'lora', 'playfair-display', 'roboto-slab', 'jetbrains-mono'];
$path = $root . '/assets/fonts';
$pinPath = __DIR__ . '/font-versions.json';
$pins = is_file($pinPath) ? json_decode((string) file_get_contents($pinPath), true) : [];
$provider = new Fontsource();
$families = [];
foreach ($ids as $id) {
    if (in_array('--resolve', $argv, true)) {
        $versions = $provider->json('https://api.fontsource.org/v1/version/' . $id);
        $pins[$id] = isset($versions['latestVariable']) ? ['latestVariable' => $versions['latestVariable'], 'latest' => $versions['latest']] : ['latest' => $versions['latest']];
        FontFiles::json($pinPath, $pins);
    }
    if (!isset($pins[$id])) {
        throw new RuntimeException('Missing pin for ' . $id . '. Resolve versions deliberately with --resolve.');
    }
    $family = $provider->prepare($id, [], $pins[$id]);
    $faces = [];
    foreach ($family['faces'] as $face) {
        $bytes = $provider->get($face['url']);
        $face['hash'] = FontFiles::validate($bytes);
        FontFiles::write($path . '/files/' . $face['hash'] . '.woff2', $bytes);
        unset($face['url'], $face['filename']);
        $faces[] = FontFiles::face($face);
    }
    FontFiles::write($path . '/licenses/' . $id . '.txt', $family['license']);
    unset($family['license']);
    $family['faces'] = $faces;
    $family['source'] = 'bundled';
    $families[] = $family;
    echo $id . ': ' . count($faces) . " faces\n";
}
FontFiles::json($path . '/manifest.json', ['families' => $families]);
