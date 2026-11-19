<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(
        public DateTimeImmutable $now,
    ) {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
