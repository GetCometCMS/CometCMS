<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Resolve supported video links locally; never fetch arbitrary URLs or accept embed HTML. */
final class VideoEmbed
{
    /** @return array{type: string, provider: string, url: string}|null */
    public static function source(array $props): ?array
    {
        $url = trim((string) ($props['video_url'] ?? ''));
        $parts = parse_url($url);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || preg_match('/[\x00-\x20\\\\]/', $url)) return null;
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);
        $autoplay = !empty($props['autoplay']);
        $muted = !empty($props['muted']) || $autoplay;
        $controls = $props['controls'] ?? true;
        $fullscreen = $props['fullscreen'] ?? true;
        $loop = !empty($props['loop']);
        $start = max(0, min(86400, (int) ($props['start_time'] ?? 0)));

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be', 'www.youtu.be'], true)) {
            $id = null;
            if (in_array($host, ['youtu.be', 'www.youtu.be'], true) && preg_match('~^/([A-Za-z0-9_-]{11})/?$~', $path, $match)) $id = $match[1];
            elseif ($path === '/watch' && is_string($query['v'] ?? null)) $id = $query['v'];
            elseif (preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~', $path, $match)) $id = $match[1];
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) return null;
            $params = ['autoplay' => (int) $autoplay, 'mute' => (int) $muted, 'controls' => (int) $controls, 'loop' => (int) $loop, 'fs' => (int) $fullscreen, 'playsinline' => 1, 'start' => $start];
            if ($loop) $params['playlist'] = $id;
            return ['type' => 'iframe', 'provider' => 'youtube', 'url' => 'https://www.youtube-nocookie.com/embed/' . $id . '?' . http_build_query($params)];
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            if (!preg_match('~^/(?:video/)?([0-9]+)(?:/([a-fA-F0-9]+))?/?$~', $path, $match)) return null;
            $params = ['autoplay' => (int) $autoplay, 'muted' => (int) $muted, 'controls' => (int) $controls, 'loop' => (int) $loop, 'fullscreen' => (int) $fullscreen, 'playsinline' => 1, 'dnt' => 1];
            // Unlisted links carry a privacy hash in either the path or query.
            $hash = $match[2] ?? ($query['h'] ?? '');
            if (is_string($hash) && preg_match('/^[a-fA-F0-9]+$/', $hash)) $params['h'] = $hash;
            return ['type' => 'iframe', 'provider' => 'vimeo', 'url' => 'https://player.vimeo.com/video/' . $match[1] . '?' . http_build_query($params) . ($start ? '#t=' . $start . 's' : '')];
        }
        if (preg_match('/\.(?:mp4|webm|ogv)$/i', $path) && strtolower($parts['scheme']) === 'https') {
            // Keep signed query strings, but replace any existing media fragment.
            return ['type' => 'file', 'provider' => 'file', 'url' => explode('#', $url, 2)[0] . ($start ? '#t=' . $start : '')];
        }
        return null;
    }
}
