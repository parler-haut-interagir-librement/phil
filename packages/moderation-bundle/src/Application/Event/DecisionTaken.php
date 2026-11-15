<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Event;

use Phil\ModerationBundle\Domain\ValueObject\Decision;

/**
 * A moderator decided on a target; the reports open on it are closed.
 */
final readonly class DecisionTaken
{
    public function __construct(
        public Decision $decision,
    ) {
    }
}
