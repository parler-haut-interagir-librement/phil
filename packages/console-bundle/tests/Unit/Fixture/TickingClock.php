<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock one second later at each reading.
 */
final class TickingClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new DateTimeImmutable('2026-03-14 09:26:53+00:00');
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        $now = $this->now;
        $this->now = $now->modify('+1 second');

        return $now;
    }
}
