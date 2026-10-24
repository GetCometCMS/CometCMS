<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\Security;
use CometCMS\Localization\Messages;

/** Bundled internal page viewer, allowed by its exact CSP hash. */
final class LinkModal
{
    public static function markup(SiteContext $ctx, bool $preview = false, ?NodeRenderer $renderer = null): string
    {
        $renderer ??= new NodeRenderer($ctx);
        $nodes = $ctx->region('modal');
        $body = $preview ? $renderer->render($nodes, ['modal_preview' => true]) : $renderer->renderPlain($nodes);
        $label = Security::e(Messages::get('modal', $ctx->htmlLang()));
        $close = Security::e(Messages::get('close', $ctx->htmlLang()));

        return '<dialog class="c-link-modal' . ($preview ? ' c-link-modal-preview' : '') . '"'
            . ($preview ? ' open' : ' data-link-modal-dialog') . ' aria-label="' . $label . '">'
            . '<div class="c-link-modal-bar"><a class="c-link-modal-title">' . $label . '</a>'
            . '<button type="button" class="c-link-modal-close" aria-label="' . $close . '"' . ($preview ? ' disabled' : ' autofocus') . '>'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M6 6l12 12M6 18L18 6"/></svg></button></div>'
            . '<div class="c-link-modal-body"' . ($preview && $ctx->annotate ? ' data-drop-root' : '') . '>' . $body . '</div></dialog>';
    }

    public static function script(): string
    {
        return (string) file_get_contents(__DIR__ . '/assets/link-modal.js') . "\ninitLinkModal(document);";
    }

    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::script(), true)) . "'";
    }
}
