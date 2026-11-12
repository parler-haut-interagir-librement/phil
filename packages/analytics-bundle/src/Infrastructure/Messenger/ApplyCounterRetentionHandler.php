<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Messenger;

use Phil\AnalyticsBundle\Application\Retention\CounterRetentionRun;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Psr\Clock\ClockInterface;

final readonly class ApplyCounterRetentionHandler
{
    public function __construct(
        private CounterRetentionRun $retention,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): void
    {
        $this->retention->runFor(UtcDay::of($this->clock->now()));
    }
}
