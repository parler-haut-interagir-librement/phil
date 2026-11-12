<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmallNumberThreshold::class)]
final class SmallNumberThresholdTest extends TestCase
{
    public function testACountMeetsTheThresholdAtItsValue(): void
    {
        $threshold = SmallNumberThreshold::of(5);

        self::assertFalse($threshold->isMetBy(4));
        self::assertTrue($threshold->isMetBy(5));
    }

    public function testTheSmallestThresholdIsTwo(): void
    {
        self::assertSame(2, SmallNumberThreshold::of(2)->value);
    }

    public function testAThresholdThatSuppressesNothingIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        SmallNumberThreshold::of(1);
    }
}
