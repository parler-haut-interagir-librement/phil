<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Runtime;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The system's time, for an application without a clock service.
 */
final readonly class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
