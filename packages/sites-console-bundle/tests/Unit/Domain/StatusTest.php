<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthState::class)]
#[CoversClass(PlanState::class)]
#[CoversClass(DeliveryOutcome::class)]
final class StatusTest extends TestCase
{
    public function testTheWorstHealth(): void
    {
        $order = [HealthState::Up, HealthState::Unknown, HealthState::Degraded, HealthState::Down];

        foreach ($order as $i => $first) {
            foreach ($order as $j => $second) {
                self::assertSame($order[max($i, $j)], $first->worst($second), $first->value.' and '.$second->value);
            }
        }
    }

    public function testFinishedPlansAndDeliveredPushes(): void
    {
        self::assertSame([false, false, true, true], array_map(static fn (PlanState $state): bool => $state->isFinished(), PlanState::cases()));
        self::assertSame([false, true, true, false], array_map(static fn (DeliveryOutcome $outcome): bool => $outcome->isDelivered(), DeliveryOutcome::cases()));
    }
}
