<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final readonly class FrozenClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-11-08T10:00:00+00:00'),
    ) {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
