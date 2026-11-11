<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ConsoleBundle\Infrastructure\Runtime\PhpMemoryGauge;
use Phil\ConsoleBundle\Infrastructure\Runtime\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpMemoryGauge::class)]
#[CoversClass(SystemClock::class)]
final class RuntimeTest extends TestCase
{
    public function testTheMemory(): void
    {
        $gauge = new PhpMemoryGauge();
        $usage = $gauge->usage();
        $peak = $gauge->peak();

        self::assertGreaterThan(0, $usage);
        self::assertGreaterThanOrEqual($usage, $peak);
        self::assertNotSame(memory_get_usage(true), $gauge->usage());
        self::assertNotSame(memory_get_peak_usage(true), $gauge->peak());
    }

    public function testTheClock(): void
    {
        $before = new DateTimeImmutable();
        $now = new SystemClock()->now();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual(new DateTimeImmutable(), $now);
    }
}
