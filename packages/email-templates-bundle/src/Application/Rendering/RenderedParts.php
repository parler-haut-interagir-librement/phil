<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

/**
 * The sources of a template rendered, before the layout: the subject and the preheader as plain text, the body as an
 * HTML fragment, and the plain-text part when the template writes it by hand.
 */
final readonly class RenderedParts
{
    public function __construct(
        public string $subject,
        public string $preheader,
        public string $html,
        public ?string $text = null,
    ) {
    }
}
