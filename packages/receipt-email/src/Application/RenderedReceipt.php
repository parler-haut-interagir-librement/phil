<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application;

/**
 * A receipt's message rendered: the subject, the preheader, the HTML and the plain-text parts, and the images the
 * HTML embeds.
 */
final readonly class RenderedReceipt
{
    /**
     * @param list<InlineImage> $images
     */
    public function __construct(
        public string $subject,
        public string $preheader,
        public string $html,
        public string $text,
        public array $images = [],
    ) {
    }
}
