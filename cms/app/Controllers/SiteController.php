<?php

declare(strict_types=1);

namespace CometCMS\Controllers;

use CometCMS\Core\Http;
use CometCMS\Site\Flags;
use CometCMS\Site\FormRepository;
use CometCMS\Site\FormSubmission;
use CometCMS\Site\FormUploads;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\PlaceholderPage;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Site\Theme;
use CometCMS\Workspaces\WorkspaceContext;
use CometCMS\Workspaces\WorkspaceRepository;

/**
 * Public website. Only answers when a workspace's website is live (it has a
 * homepage or frontend pages); otherwise the request falls through to the
 * headless behaviour.
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

    public function stylesheet(string $workspace, bool $custom = false): never
    {
        if (!(new WorkspaceRepository())->exists($workspace)) {
            $this->http->notFound();
        }

        $site = new SiteRepository(new WorkspaceContext($workspace));
        $version = $custom ? $site->customStylesheetVersion() : $site->stylesheetVersion();
        $theme = $site->theme();
        $css = $custom ? $theme['custom_css'] : Theme::stylesheet([...$theme, 'custom_css' => '']);
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

    /**
     * A website form was sent: store it as an entry of the form's content
     * type, then send the visitor back to the page the form is on. The
     * outcome travels in the URL fragment (#{anchor}-sent or -error), where
     * the form shows it with CSS alone, so the page itself stays cacheable.
     * Requests that accept JSON get the outcome as JSON instead.
     */
    public function submitForm(string $workspace, string $formId): never
    {
        if (!(new WorkspaceRepository())->exists($workspace)) {
            $this->http->notFound();
        }

        $site = new SiteRepository(new WorkspaceContext($workspace));
        $form = (new FormRepository($site))->find($formId);
        if ($form === null) {
            $this->http->notFound();
        }

        $maxBytes = max(1024, (int) comet_config('site.forms.max_body_bytes', 262144));
        foreach ((new FormRepository($site))->inputs($form) as $field) {
            if (($field['config']['type'] ?? '') === 'media') {
                $maxBytes += FormUploads::maxBytes();
                break;
            }
        }
        $result = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes
            ? ['status' => FormSubmission::ERROR, 'errors' => ['form' => 'The submission is too large.']]
            : FormSubmission::make($site)->submit($form, $_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), LayoutSchema::key($_POST['_locale'] ?? ''), $_FILES);

        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            $sent = $result['status'] === FormSubmission::SENT;
            $this->http->json($sent ? ['data' => ['status' => 'sent']] : ['error' => ['code' => 'submission_failed', 'fields' => $result['errors'] ?? []]], $sent ? 200 : 422);
        }

        $anchor = LayoutSchema::key($_POST['_anchor'] ?? '');
        $this->http->redirect($this->returnPath($_POST['_return'] ?? null) . ($anchor !== '' ? '#' . $anchor . '-' . $result['status'] : ''), 303);
    }

    /** Only a path on this site is followed, never another host. */
    private function returnPath(mixed $path): string
    {
        $path = is_string($path) ? strtok($path, '#') : '';
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\\\\\x00-\x1f]/', $path)) {
            return $this->http->url('/');
        }

        return $path;
    }

    /** A language flag of the language switcher; the same for every workspace. */
    public function flag(string $country): never
    {
        $file = Flags::file($country);
        if ($file === null) {
            $this->http->notFound();
        }

        header('Content-Type: image/svg+xml');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
        header('Cache-Control: public, max-age=604800');
        readfile($file);
        exit;
    }

    /** The public root of a headless install stays separate from the admin. */
    public function placeholder(): never
    {
        $this->send([
            'status' => 200,
            'body' => PlaceholderPage::render(),
            'content_type' => 'text/html; charset=utf-8',
        ], 'BYPASS');
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
