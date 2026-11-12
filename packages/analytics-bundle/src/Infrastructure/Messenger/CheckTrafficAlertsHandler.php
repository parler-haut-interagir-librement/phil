<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Messenger;

use Phil\AnalyticsBundle\Application\Alert\TrafficAlertRun;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Psr\Clock\ClockInterface;

final readonly class CheckTrafficAlertsHandler
{
    public function __construct(
        private TrafficAlertRun $alerts,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(): void
    {
        $this->alerts->checkFor(UtcDay::of($this->clock->now()));
    }
}
