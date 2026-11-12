<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Reading;

use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\Reading\SuppressedCounts;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SmallNumberSuppression::class)]
#[CoversClass(SuppressedCounts::class)]
final class SmallNumberSuppressionTest extends TestCase
{
    private SmallNumberSuppression $suppression;

    protected function setUp(): void
    {
        $this->suppression = new SmallNumberSuppression(SmallNumberThreshold::of(5));
    }

    public function testCountersAtOrAboveTheThresholdAreShownLargestFirst(): void
    {
        $result = $this->suppression->apply(['CA' => 5, 'FR' => 12]);

        self::assertSame(['FR' => 12, 'CA' => 5], $result->shown);
        self::assertNull($result->others);
        self::assertSame(0, $result->hidden);
    }

    public function testSmallCountersWhoseSumMeetsTheThresholdAreGroupedAsOther(): void
    {
        $result = $this->suppression->apply(['CA' => 40, 'BE' => 2, 'CH' => 3]);

        self::assertSame(['CA' => 40], $result->shown);
        self::assertSame(5, $result->others);
        self::assertSame(0, $result->hidden);
    }

    /**
     * Two counters summing to 2 would each be 1: a sum under the threshold gives its members away.
     */
    public function testAGroupWhoseSumIsUnderTheThresholdIsNotShown(): void
    {
        $result = $this->suppression->apply(['CA' => 40, 'BE' => 1, 'CH' => 1]);

        self::assertSame(['CA' => 40], $result->shown);
        self::assertNull($result->others);
        self::assertSame(2, $result->hidden);

        $result = $this->suppression->apply(['CA' => 40, 'BE' => 1, 'CH' => 2, 'DE' => 1]);
        self::assertNull($result->others);
        self::assertSame(3, $result->hidden);
    }

    /**
     * A group of one small counter would show the value it is meant to hide.
     */
    public function testASingleSmallCounterIsNotShownAtAll(): void
    {
        $result = $this->suppression->apply(['CA' => 40, 'BE' => 2]);

        self::assertSame(['CA' => 40], $result->shown);
        self::assertNull($result->others);
        self::assertSame(1, $result->hidden);
    }

    public function testTheCounterStoredAsOtherJoinsTheGroup(): void
    {
        $result = $this->suppression->apply(['home' => 40, CounterKey::OTHER => 3, 'event' => 2]);

        self::assertSame(['home' => 40], $result->shown);
        self::assertSame(5, $result->others);
    }

    /**
     * The stored "other" counter is never shown under its own name, and it is shown as the group only when the
     * group may be shown.
     */
    public function testTheStoredOtherCounterAloneIsShownOnlyAtTheThreshold(): void
    {
        self::assertSame(7, $this->suppression->apply([CounterKey::OTHER => 7])->others);
        self::assertSame([], $this->suppression->apply([CounterKey::OTHER => 7])->shown);
        self::assertNull($this->suppression->apply([CounterKey::OTHER => 4])->others);
    }

    public function testZeroCountersAreLeftOut(): void
    {
        $result = $this->suppression->apply(['CA' => 40, 'BE' => 0, 'CH' => 1]);

        self::assertNull($result->others);
        self::assertSame(1, $result->hidden);
    }

    public function testNoCounterIsNothingToShow(): void
    {
        $result = $this->suppression->apply([]);

        self::assertSame([], $result->shown);
        self::assertNull($result->others);
        self::assertSame(0, $result->hidden);
    }

    public function testANegativeCounterIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        $this->suppression->apply(['CA' => -1]);
    }
}
