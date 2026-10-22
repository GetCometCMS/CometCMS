<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\Security;

/** Navigation and contextual actions for authenticated, full-page previews. */
final class PreviewAdminBar
{
    public function __construct(private readonly SiteContext $ctx) {}

    public function render(array $state, bool $unsaved = false): string
    {
        $de = str_starts_with((string) ($this->ctx->adminUser['language'] ?? 'en'), 'de');
        $text = $de ? [
            'backend' => 'Zum Backend', 'adminBar' => 'Website-Verwaltung', 'preview' => 'Vorschau', 'notice' => 'Du verwaltest diese Website. Die Vorschau zeigt gespeicherte Inhalte, einschließlich Entwürfen.',
            'edit' => 'Seite bearbeiten', 'create' => 'Neu erstellen', 'entries' => 'Einträge', 'design' => 'Layout bearbeiten',
            'profile' => 'Mein Profil', 'language' => 'Vorschausprache',
            'draft' => 'Entwurf', 'protected' => 'Geschützt', 'published' => 'Veröffentlicht', 'scheduled' => 'Geplant', 'archived' => 'Archiviert',
        ] : [
            'backend' => 'Back to backend', 'adminBar' => 'Site administration', 'preview' => 'Preview', 'notice' => 'You are administering this website. Preview shows saved content, including drafts.',
            'edit' => 'Edit page', 'create' => 'Create new', 'entries' => 'Entries', 'design' => 'Edit layout',
            'profile' => 'My profile', 'language' => 'Preview language',
            'draft' => 'Draft', 'protected' => 'Protected', 'published' => 'Published', 'scheduled' => 'Scheduled', 'archived' => 'Archived',
        ];
        if ($unsaved) {
            $text['notice'] = $de ? 'Diese Vorschau enthält ungespeicherte Änderungen. Die Website bleibt unverändert.' : 'This preview includes unsaved changes. The website remains unchanged.';
        }
        $type = $state['type'] ?? null;
        $entry = $state['entry'] ?? null;
        $name = (string) ($type['name'] ?? '');
        $savedEntry = $entry !== null ? $this->ctx->content->findByIdentifier($name, (string) ($entry['uid'] ?? $entry['id'])) : null;
        $link = fn(string $url, string $label, string $icon, string $class = ''): string => '<a class="c-admin-action ' . $class . '" href="' . Security::e($url) . '">'
            . $this->icon($icon) . '<span>' . Security::e($label) . '</span></a>';
        $actions = '';
        if ($name !== '') {
            $collection = '/content/' . rawurlencode($name);
            if ($savedEntry !== null && $this->ctx->adminAllows('content.update', $name, $savedEntry)) {
                $actions .= $link($this->ctx->adminUrl($collection . (!empty($type['singleton']) ? '' : '/' . rawurlencode((string) ($savedEntry['slug'] ?? $savedEntry['id'])))), $text['edit'], 'edit', 'c-admin-action-primary');
            }
            if (empty($type['singleton'])) {
                if ($this->ctx->adminAllows('content.read', $name)) {
                    $actions .= $link($this->ctx->adminUrl($collection), $text['entries'], 'list');
                }
                if ($this->ctx->adminAllows('content.create', $name)) {
                    $actions .= $link($this->ctx->adminUrl($collection . '/new'), $text['create'], 'plus');
                }
            }
            if ($this->ctx->adminAllows('site.manage')) {
                $url = $this->ctx->adminUrl('/page-builder/builder/layout/' . rawurlencode($name) . '/' . ($entry !== null ? 'detail' : 'index'));
                if ($savedEntry !== null && empty($type['singleton'])) {
                    $url .= '&entry=' . rawurlencode((string) ($savedEntry['slug'] ?? $savedEntry['id']));
                }
                $actions .= $link($url, $text['design'], 'layout', 'c-admin-action-design');
            }
        }
        $status = $unsaved ? 'preview' : ($entry['status'] ?? 'preview');
        if ($entry !== null && $status === 'published' && !$this->ctx->content->isPubliclyVisible($entry)) {
            $status = 'scheduled';
        }
        $workspace = $this->ctx->site->workspaceLabel();
        $title = (string) ($entry['title'] ?? $type['label'] ?? $this->ctx->siteTitle());
        $user = $this->ctx->adminUser;
        $username = trim((string) (($user['display_name'] ?? '') ?: ($user['username'] ?? '')));
        $initials = '';
        foreach (array_slice(preg_split('/\s+/u', $username) ?: [], 0, 2) as $part) {
            preg_match('/./us', $part, $first);
            $initials .= strtoupper($first[0] ?? '');
        }
        $logo = base64_encode((string) file_get_contents(__DIR__ . '/assets/cometcms-white.png'));
        $statusClass = in_array($status, ['published', 'protected', 'draft', 'preview', 'scheduled', 'archived'], true) ? $status : 'preview';
        $statusHtml = '<span class="c-admin-status c-admin-status-' . $statusClass . '"'
            . ($statusClass === 'preview' ? ' title="' . Security::e($text['notice']) . '"' : '')
            . '><span class="c-admin-status-dot" aria-hidden="true"></span>'
            . Security::e($text[$statusClass]) . '</span>';

        return '<nav class="c-preview-bar" aria-label="' . Security::e($text['adminBar']) . '"><div class="c-admin-shell">'
            . '<a class="c-admin-brand" href="' . Security::e($this->ctx->adminUrl('/dashboard')) . '" aria-label="CometCMS · ' . Security::e($text['backend']) . '" title="' . Security::e($text['backend']) . '">'
            . '<img src="data:image/png;base64,' . $logo . '" alt="CometCMS" width="138" height="30">' . $this->icon('back') . '</a>'
            . '<div class="c-admin-context" title="' . Security::e($workspace . ' · ' . $title) . '">' . $this->icon('page')
            . '<div><span class="c-admin-workspace">' . Security::e($workspace) . '</span><span class="c-admin-title">' . Security::e($title) . '</span></div></div>'
            . '<div class="c-admin-meta">' . $statusHtml . $this->languages($type, $entry, $text['language']) . '</div>'
            . '<div class="c-preview-actions">' . $actions . '</div>'
            . '<details class="c-admin-account c-admin-dropdown"><summary aria-label="' . Security::e($username) . '" title="' . Security::e($username) . '"><span class="c-admin-avatar" aria-hidden="true">' . Security::e($initials) . '</span>'
            . '<span class="c-admin-username">' . Security::e($username) . '</span>' . $this->icon('chevron') . '</summary>'
            . '<div class="c-admin-dropdown-panel">' . $link($this->ctx->adminUrl('/profile'), $text['profile'], 'user')
            . $link($this->ctx->adminUrl('/dashboard'), $text['backend'], 'back') . '</div></details>'
            . '</div></nav>';
    }

    private function languages(?array $type, ?array $entry, string $label): string
    {
        $locales = $this->ctx->locales();
        if ($type !== null && !empty($type['locales'])) {
            $locales = array_values(array_intersect($locales, $type['locales']));
        }
        $current = strtoupper($this->ctx->locale ?: $this->ctx->defaultLocale());
        $value = $this->icon('globe') . '<span>' . Security::e($current) . '</span>';
        if (count($locales) < 2) {
            return '<span class="c-admin-language" title="' . Security::e($label) . '">' . $value . '</span>';
        }
        $links = '';
        foreach ($locales as $locale) {
            if ($entry !== null && $type !== null) {
                // Entry previews also support drafts without a live route.
                $url = $this->ctx->adminUrl('/site-preview/' . rawurlencode($type['name']) . '/' . rawurlencode((string) ($entry['slug'] ?? $entry['id'])));
                $url = preg_replace('/([?&])locale=[^&]*/', '$1locale=' . rawurlencode($locale), $url);
                if ($this->ctx->previewToken !== '') $url .= '&snapshot=' . rawurlencode($this->ctx->previewToken);
            } else {
                $url = $type !== null ? $this->ctx->indexUrl($type['name'], $locale) : null;
                $url ??= $this->ctx->homeUrl($locale);
            }
            $active = $locale === ($this->ctx->locale ?: $this->ctx->defaultLocale());
            $links .= '<a class="c-admin-action" href="' . Security::e($url) . '" lang="' . Security::e($locale) . '"'
                . ($active ? ' aria-current="true"' : '') . '><span>' . Security::e(strtoupper($locale)) . '</span>' . ($active ? $this->icon('check') : '') . '</a>';
        }
        return '<details class="c-admin-dropdown c-admin-languages"><summary class="c-admin-language" aria-label="' . Security::e($label) . '">'
            . $value . $this->icon('chevron') . '</summary><div class="c-admin-dropdown-panel">' . $links . '</div></details>';
    }

    private function icon(string $name): string
    {
        $paths = [
            'back' => '<path d="m9 5-7 7 7 7M2 12h19"/>',
            'page' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8ZM14 2v6h6M8 13h8M8 17h5"/>',
            'edit' => '<path d="m16 3 5 5M4 15 16 3a2 2 0 0 1 5 5L9 20l-6 1Z"/>',
            'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
            'plus' => '<path d="M12 4v16M4 12h16"/>',
            'layout' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
            'chevron' => '<path d="m6 9 6 6 6-6"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        ];
        return '<svg class="c-admin-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
    }
}
