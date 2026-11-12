<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\BrokenFlow;

use DateTimeImmutable;
use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Infrastructure\BrokenFlow\GoalCompletionHistory;
use Phil\AnalyticsBundle\Tests\Unit\Fixture\InMemoryCounters;
use Phil\BrokenFlowAlerts\Flow;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GoalCompletionHistory::class)]
final class GoalCompletionHistoryTest extends TestCase
{
    public function testTheWatchedGoalsAreFlows(): void
    {
        $history = new GoalCompletionHistory(new InMemoryCounters(), [
            'checkout' => ['site' => 's', 'goal' => 'ticket', 'label' => 'Tickets'],
            'contact' => ['site' => 's', 'goal' => 'sent', 'label' => null],
        ]);

        self::assertEquals([Flow::of('checkout', 'Tickets'), Flow::of('contact')], $history->flows());
    }

    public function testACompletionIsTheGoalCounterOfTheDay(): void
    {
        $store = new InMemoryCounters();

        foreach (['2026-11-09', '2026-11-11', '2026-11-11'] as $day) {
            $store->increment(CounterKey::of(SiteId::fromString('s'), UtcDay::fromString($day), 'goals', 'goal', 'ticket'));
        }

        $store->increment(CounterKey::of(SiteId::fromString('s'), UtcDay::fromString('2026-11-10'), 'goals', 'goal', 'other'));
        $store->increment(CounterKey::of(SiteId::fromString('t'), UtcDay::fromString('2026-11-10'), 'goals', 'goal', 'ticket'));

        $history = new GoalCompletionHistory($store, ['checkout' => ['site' => 's', 'goal' => 'ticket', 'label' => null]]);

        self::assertSame([1, 0, 2, 0], $history->dailyCompletions(Flow::of('checkout'), new DateTimeImmutable('2026-11-09 00:00:00 UTC'), 4));
    }

    public function testAFlowNotWatchedIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('No goal is watched as the flow "elsewhere".');

        new GoalCompletionHistory(new InMemoryCounters(), [])->dailyCompletions(Flow::of('elsewhere'), new DateTimeImmutable('2026-11-09'), 1);
    }
}
