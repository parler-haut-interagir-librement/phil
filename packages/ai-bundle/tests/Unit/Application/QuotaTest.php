<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Application;

use Override;
use Phil\AiBundle\Application\Quota;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\UsageMeter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Quota::class)]
final class QuotaTest extends TestCase
{
    private UsageMeter $meter;

    private Subject $subject;

    #[Override]
    protected function setUp(): void
    {
        $this->meter = new UsageMeter(
            new Meters(new Meter('ai_tokens', CalendarPeriod::month(), Limit::of(100)), new Meter('ai_tasks', CalendarPeriod::month(), Limit::of(2)), new Meter('ai_budget', CalendarPeriod::month(), Limit::of(1000)), new Meter('ai_ceiling', CalendarPeriod::month(), Limit::of(1500))),
            new InMemoryUsageStore(),
            new MockClock('2026-11-06 10:00:00 UTC'),
        );
        $this->subject = Subject::fromString('project-1');
    }

    public function testTokensAreCheckedForTheReserveAndChargedWhenSpent(): void
    {
        $quota = new Quota($this->meter, 'ai_tokens');

        $quota->admit($this->subject, KeySource::Application, 100, 't-1');
        $quota->charge($this->subject, KeySource::Application, new Tokens(30, 30), $this->usd(0), 't-1');
        self::assertSame(60, $this->used('ai_tokens'));

        $quota->admit($this->subject, KeySource::Application, 40, 't-2');
        $quota->charge($this->subject, KeySource::Application, new Tokens(0, 0), $this->usd(0), 't-2');
        $quota->charge($this->subject, KeySource::Application, new Tokens(20, 0), $this->usd(0), 't-1');
        self::assertSame(60, $this->used('ai_tokens'), 'Nothing spent, or a task charged twice, counts nothing.');

        try {
            $quota->admit($this->subject, KeySource::Application, 41, 't-3');
            self::fail('A reserve past the quota was admitted.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame([41, 60], [$quotaExceeded->requested, $quotaExceeded->usage->used]);
        }

        $quota->charge($this->subject, KeySource::Application, new Tokens(30, 30), $this->usd(0), 't-4');
        self::assertSame(120, $this->used('ai_tokens'), 'What was spent is charged, over the limit if need be.');
        self::assertSame(0, $this->used('ai_tasks'));
    }

    public function testOneTaskIsConsumedBeforeEachStarts(): void
    {
        $quota = new Quota($this->meter, null, 'ai_tasks');

        $quota->admit($this->subject, KeySource::Application, 1_000, 't-1');
        $quota->admit($this->subject, KeySource::Application, 1_000, 't-1');
        $quota->admit($this->subject, KeySource::Application, 1_000, 't-2');
        $quota->charge($this->subject, KeySource::Application, new Tokens(500, 500), $this->usd(0), 't-2');
        self::assertSame([2, 0], [$this->used('ai_tasks'), $this->used('ai_tokens')]);

        $this->expectException(QuotaExceeded::class);
        $quota->admit($this->subject, KeySource::Application, 1, 't-3');
    }

    public function testTheTokensAreCheckedBeforeATaskIsConsumed(): void
    {
        $quota = new Quota($this->meter, 'ai_tokens', 'ai_tasks');

        try {
            $quota->admit($this->subject, KeySource::Application, 101, 't-1');
            self::fail('A reserve past the quota was admitted.');
        } catch (QuotaExceeded) {
            self::assertSame(0, $this->used('ai_tasks'));
        }
    }

    public function testThePersonsOwnKeyIsNotCountedUnlessConfigured(): void
    {
        $quota = new Quota($this->meter, 'ai_tokens', 'ai_tasks');

        $quota->admit($this->subject, KeySource::User, 1_000, 't-1');
        $quota->charge($this->subject, KeySource::User, new Tokens(500, 500), $this->usd(0), 't-1');
        self::assertSame([0, 0], [$this->used('ai_tasks'), $this->used('ai_tokens')]);

        $counted = new Quota($this->meter, 'ai_tokens', 'ai_tasks', true);
        $counted->admit($this->subject, KeySource::User, 100, 't-2');
        $counted->charge($this->subject, KeySource::User, new Tokens(5, 5), $this->usd(0), 't-2');
        self::assertSame([1, 10], [$this->used('ai_tasks'), $this->used('ai_tokens')]);
    }

    public function testAFailedCallGivesItsTaskBack(): void
    {
        $quota = new Quota($this->meter, null, 'ai_tasks');
        $quota->admit($this->subject, KeySource::Application, 1, 't-1');
        $quota->admit($this->subject, KeySource::Application, 1, 't-2');

        $quota->release($this->subject, KeySource::Application, 't-1');
        $quota->release($this->subject, KeySource::Application, 't-1');
        self::assertSame(1, $this->used('ai_tasks'));

        $quota->admit($this->subject, KeySource::Application, 1, 't-3');
        self::assertSame(2, $this->used('ai_tasks'));

        $quota->release($this->subject, KeySource::User, 't-3');
        new Quota($this->meter, 'ai_tokens')->release($this->subject, KeySource::Application, 't-3');
        self::assertSame(2, $this->used('ai_tasks'), 'A key the quota does not count, or a quota without tasks, gives nothing back.');

        new Quota($this->meter, null, 'ai_tasks', true)->release($this->subject, KeySource::User, 't-3');
        self::assertSame(1, $this->used('ai_tasks'));
    }

    public function testTheBudgetMustHaveRoomAndIsChargedWithTheCost(): void
    {
        $quota = new Quota($this->meter, budgetMeter: 'ai_budget');

        $quota->admit($this->subject, KeySource::Application, 1_000_000, 't-1');
        $quota->charge($this->subject, KeySource::Application, new Tokens(10, 10), $this->usd(999), 't-1');
        $quota->charge($this->subject, KeySource::Application, new Tokens(10, 10), $this->usd(0), 't-2');
        self::assertSame(999, $this->used('ai_budget'));

        $quota->admit($this->subject, KeySource::Application, 1, 't-3');
        $quota->charge($this->subject, KeySource::Application, Tokens::none(), $this->usd(5), 't-3');
        self::assertSame([1004, 0], [$this->used('ai_budget'), $this->used('ai_tokens')]);

        try {
            $quota->admit($this->subject, KeySource::Application, 1, 't-4');
            self::fail('A task was admitted past its budget.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(['ai_budget', 1], [$quotaExceeded->usage->meter, $quotaExceeded->requested]);
        }

        $quota->admit($this->subject, KeySource::User, 1, 't-5');
        $quota->charge($this->subject, KeySource::User, Tokens::none(), $this->usd(50), 't-5');
        self::assertSame(1004, $this->used('ai_budget'), "The person's own key spends nothing of the budget.");
    }

    public function testTheCeilingCountsTheWholeApplicationOnItsSubject(): void
    {
        $quota = new Quota($this->meter, ceilingMeter: 'ai_ceiling', ceilingSubject: 'site');
        $other = Subject::fromString('project-2');

        $quota->charge($this->subject, KeySource::Application, Tokens::none(), $this->usd(1000), 't-1');
        $quota->charge($other, KeySource::Application, Tokens::none(), $this->usd(499), 't-2');
        self::assertSame([1499, 0, 0], [$this->meter->usage('site', 'ai_ceiling')->used, $this->used('ai_ceiling'), $this->used('ai_budget')]);

        $quota->admit($other, KeySource::Application, 1, 't-3');
        $quota->charge($other, KeySource::Application, Tokens::none(), $this->usd(1), 't-3');

        try {
            $quota->admit(Subject::fromString('project-3'), KeySource::Application, 1, 't-4');
            self::fail('A task was admitted past the ceiling.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(['site', 'ai_ceiling'], [$quotaExceeded->usage->subject, $quotaExceeded->usage->meter]);
        }

        new Quota($this->meter, ceilingMeter: 'ai_ceiling')->charge($this->subject, KeySource::Application, Tokens::none(), $this->usd(7), 't-5');
        self::assertSame(7, $this->meter->usage(Quota::APPLICATION_SUBJECT, 'ai_ceiling')->used);
    }

    /**
     * @phpstan-impure
     */
    private function used(string $meter): int
    {
        return $this->meter->usage($this->subject->value, $meter)->used;
    }

    private function usd(int $micros): Cost
    {
        return new Cost($micros, 'USD');
    }
}
