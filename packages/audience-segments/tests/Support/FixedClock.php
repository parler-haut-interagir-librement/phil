<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-11-21 10:00:00'))
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
