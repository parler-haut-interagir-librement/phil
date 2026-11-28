<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock the test moves by hand.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(
        public DateTimeImmutable $now = new DateTimeImmutable('2026-11-01 12:00:00 UTC'),
    ) {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function move(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
