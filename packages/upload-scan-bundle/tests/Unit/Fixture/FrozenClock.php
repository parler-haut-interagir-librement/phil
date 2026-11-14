<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final readonly class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
