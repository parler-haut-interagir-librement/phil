<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\Status\DecisionAction;

/**
 * A line of the public journal: what was decided, on what type of content, under which role, why and when. Never the
 * moderator, never the content's author.
 */
final readonly class JournalEntry
{
    public function __construct(
        public DecisionAction $action,
        public string $targetType,
        public string $role,
        public ?string $reason,
        public DateTimeImmutable $decidedAt,
    ) {
    }
}
