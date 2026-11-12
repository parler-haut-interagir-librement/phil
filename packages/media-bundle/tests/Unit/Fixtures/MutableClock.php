<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A clock the test moves forward.
 */
final class MutableClock implements ClockInterface
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
