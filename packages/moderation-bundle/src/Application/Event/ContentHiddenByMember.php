<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Event;

use Phil\ModerationBundle\Domain\ValueObject\Report;

/**
 * A trusted member hid a content until the moderation reviews it.
 */
final readonly class ContentHiddenByMember
{
    public function __construct(
        public Report $report,
    ) {
    }
}
