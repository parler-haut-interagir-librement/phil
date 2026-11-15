<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Event;

use Phil\ModerationBundle\Domain\ValueObject\Report;

/**
 * A content waits for the moderation before it is published: the first post of a member, or a text the watched words held.
 */
final readonly class ContentHeld
{
    public function __construct(
        public Report $report,
    ) {
    }
}
