<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application;

/**
 * The embed a request is answered for: which one, for which origin, in which theme, about which subject, and the
 * themes the page that holds it may switch to.
 */
final readonly class EmbedContext
{
    /**
     * @param list<string> $themes
     */
    public function __construct(
        public string $embed,
        public string $origin,
        public ?string $theme,
        public ?string $subject,
        public array $themes,
    ) {
    }
}
