<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Domain;

use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComponentStatus::class)]
final class ComponentStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{ComponentStatus, ComponentStatus, ComponentStatus}>
     */
    public static function pairs(): iterable
    {
        $order = [ComponentStatus::Operational, ComponentStatus::Maintenance, ComponentStatus::Degraded, ComponentStatus::PartialOutage, ComponentStatus::MajorOutage];

        foreach ($order as $i => $a) {
            foreach ($order as $j => $b) {
                yield $a->value.' and '.$b->value => [$a, $b, $order[max($i, $j)]];
            }
        }
    }

    #[DataProvider('pairs')]
    public function testTheWorstOfTwoFollowsTheOrderOfTheCases(ComponentStatus $a, ComponentStatus $b, ComponentStatus $worst): void
    {
        self::assertSame($worst, $a->worst($b));
    }

    public function testOnlyDegradedAndOutagesAreTheImpactOfAnIncident(): void
    {
        self::assertFalse(ComponentStatus::Operational->isOutage());
        self::assertFalse(ComponentStatus::Maintenance->isOutage());
        self::assertTrue(ComponentStatus::Degraded->isOutage());
        self::assertTrue(ComponentStatus::PartialOutage->isOutage());
        self::assertTrue(ComponentStatus::MajorOutage->isOutage());
    }
}
