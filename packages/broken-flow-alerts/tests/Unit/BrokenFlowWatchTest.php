<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts\Tests\Unit;

use Phil\BrokenFlowAlerts\BrokenFlowRule;
use Phil\BrokenFlowAlerts\BrokenFlowWatch;
use Phil\BrokenFlowAlerts\Exception\InvalidFlowValue;
use Phil\BrokenFlowAlerts\Flow;
use Phil\BrokenFlowAlerts\FlowAlert;
use Phil\BrokenFlowAlerts\FlowAlertKind;
use Phil\BrokenFlowAlerts\Tests\Unit\Fixture\RecordingSender;
use Phil\BrokenFlowAlerts\Tests\Unit\Fixture\ScriptedHistory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(BrokenFlowWatch::class)]
#[CoversClass(FlowAlert::class)]
#[CoversClass(FlowAlertKind::class)]
final class BrokenFlowWatchTest extends TestCase
{
    /**
     * Late in the evening of 2026-11-11 in Montréal, already the 12th in UTC: the last whole day is the 11th.
     */
    private const string NOW = '2026-11-11 21:30:00 America/Toronto';

    public function testAFlowIsReportedOnTheDayItBreaks(): void
    {
        $sender = new RecordingSender();
        $history = new ScriptedHistory(['checkout' => [...array_fill(0, 35, 20), 0]]);

        $alerts = $this->watch($history, $sender)->check([Flow::of('checkout', 'Checkout')]);

        self::assertCount(1, $alerts);
        self::assertSame($alerts, $sender->sent);
        self::assertSame('checkout', $alerts[0]->flow->id);
        self::assertSame(FlowAlertKind::Broken, $alerts[0]->kind);
        self::assertSame('2026-11-11', $alerts[0]->day);
        self::assertSame('2026-11-11', $alerts[0]->since);
        self::assertSame(0, $alerts[0]->observed);
        self::assertEqualsWithDelta(20.0, $alerts[0]->expected, 1e-9);
    }

    /**
     * The history asked for ends on the last whole UTC day: 36 days, from 2026-10-07 to 2026-11-11.
     */
    public function testTheHistoryEndsOnTheLastWholeUtcDay(): void
    {
        $history = new ScriptedHistory(['checkout' => array_fill(0, 36, 20)]);

        $this->watch($history, new RecordingSender())->check([Flow::of('checkout')]);

        self::assertSame([['checkout', '2026-10-07T00:00:00+00:00', 36]], $history->asked);
    }

    public function testAFlowThatStaysBrokenIsNotReportedAgain(): void
    {
        $sender = new RecordingSender();
        $history = new ScriptedHistory(['checkout' => [...array_fill(0, 34, 20), 0, 0]]);

        self::assertSame([], $this->watch($history, $sender)->check([Flow::of('checkout')]));
        self::assertSame([], $sender->sent);
    }

    public function testAWorkingFlowIsNotReported(): void
    {
        $history = new ScriptedHistory(['checkout' => array_fill(0, 36, 20)]);

        self::assertSame([], $this->watch($history, new RecordingSender())->check([Flow::of('checkout')]));
    }

    public function testARecoveryIsReported(): void
    {
        // A window of one day, so that the silence of the day before has left the stretch the rule weighs.
        $rule = new BrokenFlowRule(baselineDays: 28, windowDays: 1);
        $history = new ScriptedHistory(['checkout' => [...array_fill(0, 28, 20), 0, 20]]);

        $alerts = $this->watch($history, new RecordingSender(), $rule)->check([Flow::of('checkout')]);

        self::assertCount(1, $alerts);
        self::assertSame(FlowAlertKind::Recovered, $alerts[0]->kind);
        self::assertSame('2026-11-11', $alerts[0]->day);
        self::assertSame('2026-11-11', $alerts[0]->since);
        self::assertSame(20, $alerts[0]->observed);
    }

    /**
     * A silence long enough to become the rhythm ends the alert without a recovery.
     */
    public function testASilenceThatBecameTheRhythmIsNotARecovery(): void
    {
        $rule = new BrokenFlowRule(baselineDays: 7, windowDays: 1, weekly: false);
        // The day before, a silent day after a usual day of ten is broken; today, the usual day is zero.
        $history = new ScriptedHistory(['checkout' => [70, 0, 0, 0, 0, 0, 0, 0, 0]]);

        self::assertSame([], $this->watch($history, new RecordingSender(), $rule)->check([Flow::of('checkout')]));
    }

    public function testTheStretchOfABreakStartsOnItsFirstSilentDay(): void
    {
        $history = new ScriptedHistory(['rare' => [...array_fill(0, 32, 2), 0, 0, 0, 0]]);

        $alerts = $this->watch($history, new RecordingSender())->check([Flow::of('rare')]);

        self::assertCount(1, $alerts);
        self::assertSame('2026-11-08', $alerts[0]->since);
        self::assertSame('2026-11-11', $alerts[0]->day);
    }

    public function testEachFlowIsCheckedInOrder(): void
    {
        $history = new ScriptedHistory([
            'checkout' => [...array_fill(0, 35, 20), 0],
            'signup' => array_fill(0, 36, 20),
            'contact' => [...array_fill(0, 35, 30), 0],
        ]);

        $alerts = $this->watch($history, new RecordingSender())->check([Flow::of('checkout'), Flow::of('signup'), Flow::of('contact')]);

        self::assertSame(['checkout', 'contact'], array_map(static fn (FlowAlert $alert): string => $alert->flow->id, $alerts));
        self::assertCount(3, $history->asked);
    }

    public function testAHistoryOfTheWrongLengthIsRefused(): void
    {
        $history = new ScriptedHistory(['checkout' => array_fill(0, 35, 20)]);

        $this->expectException(InvalidFlowValue::class);
        $this->expectExceptionMessageIsOrContains('The history of flow "checkout" has 35 days; 36 were asked for.');

        $this->watch($history, new RecordingSender())->check([Flow::of('checkout')]);
    }

    private function watch(ScriptedHistory $history, RecordingSender $sender, ?BrokenFlowRule $rule = null): BrokenFlowWatch
    {
        $clock = new MockClock(self::NOW);

        return $rule instanceof BrokenFlowRule ? new BrokenFlowWatch($history, $sender, $clock, $rule) : new BrokenFlowWatch($history, $sender, $clock);
    }
}
