<?php

declare(strict_types=1);

namespace CometCMS\Controllers;

use CometCMS\Core\Http;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Site\Theme;
use CometCMS\Workspaces\WorkspaceContext;
use CometCMS\Workspaces\WorkspaceRepository;

/**
 * Public website. Only answers when a workspace has its website enabled;
 * otherwise the request falls through to the headless behaviour.
 */
final class SiteController
{
    /**
     * Rendered pages never run scripts: the site ships none, and this keeps
     * it that way even if content or custom CSS were to contain markup.
     */
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; img-src 'self' data: https:; media-src 'self' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' data: https:; script-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    public function __construct(private readonly Http $http) {}

    /** Whether $path may belong to the website rather than to CometCMS itself. */
    public static function handles(string $path): bool
    {
        foreach (SiteRepository::RESERVED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        return true;
    }

    /** Send the page and exit, or return when no website answers this host. */
    public function page(string $path): void
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $workspace = SiteService::workspaceForHost($host);

        if ($workspace === null) {
            return;
        }

        WorkspaceContext::setActive($workspace->slug());
        $context = SiteContext::make(new SiteRepository($workspace), rtrim($this->http->url('/'), '/'), $this->origin());
        $response = (new SiteService($context))->respondCached($path, $_GET);
        $this->send($response, $response['cache_status']);
    }

    public function stylesheet(string $workspace): never
    {
        if (!(new WorkspaceRepository())->exists($workspace)) {
            $this->http->notFound();
        }

        $site = new SiteRepository(new WorkspaceContext($workspace));
        $version = $site->stylesheetVersion();
        $css = Theme::stylesheet($site->theme());
        $etag = '"' . $version . '"';

        header('Content-Type: text/css; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('ETag: ' . $etag);
        header(($_GET['v'] ?? '') === $version ? 'Cache-Control: public, max-age=31536000, immutable' : 'Cache-Control: public, max-age=300');

        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }

        echo $css;
        exit;
    }

    private function send(array $response, string $cacheStatus): never
    {
        $etag = '"' . substr(sha1($response['body']), 0, 20) . '"';

        http_response_code((int) $response['status']);
        header('Content-Type: ' . $response['content_type']);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-CometCMS-Cache: ' . $cacheStatus);
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('ETag: ' . $etag);

        if (str_starts_with($response['content_type'], 'text/html')) {
            header('Content-Security-Policy: ' . self::CONTENT_SECURITY_POLICY);
        }

        if ($response['status'] === 200 && trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }

        echo $response['body'];
        exit;
    }

    private function origin(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host)) {
            return '';
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return $scheme . '://' . $host;
    }
}
