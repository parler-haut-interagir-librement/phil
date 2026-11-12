<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final class FrozenClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ) {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(string $instant): void
    {
        $this->now = new DateTimeImmutable($instant);
    }
}
