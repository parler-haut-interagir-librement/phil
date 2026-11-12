<?php

declare(strict_types=1);

namespace Phil\SocialCards\Preview;

/**
 * One thing `TagCheck` found in a page's tags: how much it matters, the tag it is about, and what is wrong.
 */
final readonly class Finding
{
    public function __construct(
        public Severity $severity,
        public string $tag,
        public string $message,
    ) {
    }
}
