<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\ValueObject;

use Phil\ReactionsBundle\Domain\Status\Audience;

/**
 * Who sees the reactions of a content at all, who sees their counters, and who sees the names of those who reacted.
 */
final readonly class ReactionVisibility
{
    public function __construct(
        public Audience $panel,
        public Audience $counts,
        public Audience $names,
    ) {
    }
}
