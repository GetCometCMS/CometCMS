<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\Security;

/** Navigation and contextual actions for authenticated, full-page previews. */
final class PreviewAdminBar
{
    public function __construct(private readonly SiteContext $ctx) {}

    public function render(array $state): string
    {
        $de = str_starts_with((string) ($this->ctx->adminUser['language'] ?? 'en'), 'de');
        $text = $de ? [
            'backend' => 'Zum Backend', 'preview' => 'Vorschau', 'notice' => 'Du verwaltest diese Website. Änderungen sind noch nicht veröffentlicht.',
            'edit' => 'Seite bearbeiten', 'create' => 'Neu erstellen', 'entries' => 'Einträge', 'design' => 'Layout bearbeiten',
            'draft' => 'Entwurf', 'published' => 'Veröffentlicht', 'scheduled' => 'Geplant', 'archived' => 'Archiviert',
        ] : [
            'backend' => 'Back to backend', 'preview' => 'Preview', 'notice' => 'You are administering this website. Changes may not be published yet.',
            'edit' => 'Edit page', 'create' => 'Create new', 'entries' => 'Entries', 'design' => 'Edit layout',
            'draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'archived' => 'Archived',
        ];
        $type = $state['type'] ?? null;
        $entry = $state['entry'] ?? null;
        $name = (string) ($type['name'] ?? '');
        $link = static fn(string $url, string $label): string => '<a href="' . Security::e($url) . '">' . Security::e($label) . '</a>';
        $actions = '';
        if ($name !== '') {
            $collection = '/content/' . rawurlencode($name);
            if ($entry !== null && $this->ctx->adminAllows('content.update', $name, $entry)) {
                $actions .= $link($this->ctx->adminUrl($collection . (!empty($type['singleton']) ? '' : '/' . rawurlencode((string) ($entry['slug'] ?? $entry['id'])))), $text['edit']);
            }
            if (empty($type['singleton'])) {
                if ($this->ctx->adminAllows('content.read', $name)) {
                    $actions .= $link($this->ctx->adminUrl($collection), $text['entries']);
                }
                if ($this->ctx->adminAllows('content.create', $name)) {
                    $actions .= $link($this->ctx->adminUrl($collection . '/new'), $text['create']);
                }
            }
            if ($this->ctx->adminAllows('site.manage')) {
                $url = $this->ctx->adminUrl('/page-builder/builder/layout/' . rawurlencode($name) . '/' . ($entry !== null ? 'detail' : 'index'));
                if ($entry !== null && empty($type['singleton'])) {
                    $url .= '&entry=' . rawurlencode((string) ($entry['slug'] ?? $entry['id']));
                }
                $actions .= $link($url, $text['design']);
            }
        }
        $status = $entry['status'] ?? '';
        if ($entry !== null && $status === 'published' && !$this->ctx->content->isPubliclyVisible($entry)) {
            $status = 'scheduled';
        }
        $details = array_filter([
            $this->ctx->site->workspaceLabel(),
            $entry['title'] ?? $type['label'] ?? '',
            $text[$status] ?? $status,
            strtoupper($this->ctx->locale),
        ], static fn($value): bool => $value !== '');
        $user = $this->ctx->adminUser;

        return '<nav class="c-preview-bar" aria-label="' . Security::e($text['preview']) . '">'
            . '<div class="c-preview-bar-main">' . $link($this->ctx->adminUrl('/dashboard'), 'CometCMS · ' . $text['backend'])
            . '<span class="c-preview-badge" title="' . Security::e($text['notice']) . '">' . Security::e($text['preview']) . '</span>'
            . '<span class="c-preview-details">' . Security::e(implode(' · ', $details)) . '</span>'
            . '<span class="c-preview-user">' . Security::e((string) (($user['display_name'] ?? '') ?: ($user['username'] ?? ''))) . '</span></div>'
            . ($actions !== '' ? '<div class="c-preview-actions">' . $actions . '</div>' : '') . '</nav>';
    }
}
