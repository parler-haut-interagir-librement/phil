<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Content;

/**
 * A message ready to send: its subject, its HTML and its text.
 */
final readonly class RenderedMessage
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
    ) {
    }
}
