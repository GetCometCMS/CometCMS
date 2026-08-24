<?php

declare(strict_types=1);

namespace CometCMS\Core;

final class Http
{
    public function path(): string
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $path = '/' . ltrim($uri, '/');

        return rtrim($path, '/') ?: '/';
    }

    public function url(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $base = $base === '/' ? '' : $base;

        return $base . '/' . ltrim($path, '/');
    }

    public function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function text(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    /**
     * Send a file without retaining it in PHP's output buffers.
     *
     * @param array<string, string> $headers
     */
    public function streamFile(string $path, string $contentType, array $headers = []): never
    {
        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Could not open file for streaming.');
        }

        $stat = fstat($stream);

        // The entry point buffers normal responses so fatal errors can be replaced
        // cleanly. File responses must bypass those buffers to keep memory usage
        // constant regardless of the file size.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string) ($stat['size'] ?? filesize($path)));
        header('X-Content-Type-Options: nosniff');

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        fpassthru($stream);
        fclose($stream);
        exit;
    }

    public function notFound(): never
    {
        $this->text('Not found', 404);
    }

    public function requestJson(): array
    {
        $body = file_get_contents('php://input') ?: '';
        $json = json_decode($body, true);

        return is_array($json) ? $json : [];
    }

    public function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;

            return null;
        }

        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);

        return is_string($value) ? $value : null;
    }
}
