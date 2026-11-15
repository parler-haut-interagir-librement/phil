<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;

/**
 * A target as a moderator reviews it: what it is, its open reports, and its history of decisions.
 */
final readonly class QueueEntry
{
    /**
     * @param list<Report>   $reports   the open reports, the earliest first
     * @param list<Decision> $decisions the decisions, the latest first
     */
    public function __construct(
        public TargetReference $target,
        public ?TargetView $view,
        public array $reports,
        public array $decisions,
    ) {
    }
}
