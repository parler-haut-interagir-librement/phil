<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\View;

/**
 * One reaction as a viewer sees it: `count` is null when the viewer does not see its counter, and always for a private
 * reaction; only an `interactive` reaction can be pressed.
 */
final readonly class ReactionItem
{
    public function __construct(
        public string $code,
        public string $label,
        public ?string $emoji,
        public ?string $image,
        public ?string $imageAlt,
        public ?int $count,
        public ?string $countLabel,
        public bool $pressed,
        public bool $interactive,
    ) {
    }
}
