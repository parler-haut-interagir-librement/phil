<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;

/**
 * One name badge: the name in large letters, a line under it — pronouns, a role —, and an organisation at the foot.
 */
final readonly class Badge
{
    public function __construct(
        public string $name,
        public ?string $subtitle = null,
        public ?string $organisation = null,
    ) {
        if ('' === trim($name)) {
            throw InvalidDocument::because('A badge has a name.');
        }
    }
}
