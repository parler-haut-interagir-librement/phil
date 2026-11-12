<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class Clock implements ClockInterface
{
    public function __construct(
        public DateTimeImmutable $now,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
