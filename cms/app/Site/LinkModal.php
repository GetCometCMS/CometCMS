<?php

declare(strict_types=1);

namespace CometCMS\Site;

/**
 * Internal links that open in a modal: the modal template (a dialog) and
 * its bundled script, allowed by its exact CSP hash. The script shows the
 * linked page embedded (see SiteContext::$embed) in the template's site
 * content element.
 */
final class LinkModal
{
    public static function markup(SiteContext $ctx, NodeRenderer $renderer, array $state = []): string
    {
        return $renderer->renderPlain($ctx->region(LayoutSchema::MODAL), $state);
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
