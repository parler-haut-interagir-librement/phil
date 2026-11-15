<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\Status\ReportSource;

/**
 * A line of the queue: a target with open reports, how many, when the first and the last came, their reasons and
 * sources.
 */
final readonly class QueueItem
{
    /**
     * @param list<string>       $reasons each once
     * @param list<ReportSource> $sources each once
     */
    public function __construct(
        public TargetReference $target,
        public int $reports,
        public DateTimeImmutable $firstReportedAt,
        public DateTimeImmutable $lastReportedAt,
        public array $reasons,
        public array $sources,
    ) {
    }

    public function isHeld(): bool
    {
        return \in_array(ReportSource::Sandbox, $this->sources, true) || \in_array(ReportSource::WatchedWords, $this->sources, true);
    }
}
