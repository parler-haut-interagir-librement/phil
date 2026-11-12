<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Messenger;

use Phil\AnalyticsBundle\Application\Report\DueReports;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Psr\Clock\ClockInterface;

final readonly class SendDueReportsHandler
{
    public function __construct(
        private DueReports $reports,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): void
    {
        $this->reports->sendFor(UtcDay::of($this->clock->now()));
    }
}
