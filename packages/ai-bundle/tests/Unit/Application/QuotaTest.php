<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Application;

use Override;
use Phil\AiBundle\Application\Exception\TooManyTasks;
use Phil\AiBundle\Application\Quota;
use Phil\AiBundle\Domain\Exception\InvalidAiValue;
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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\LockStore;

#[CoversClass(Quota::class)]
#[CoversClass(TooManyTasks::class)]
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

    public function testTheTokensAreReservedThenSettledWithWhatWasSpent(): void
    {
        $quota = new Quota($this->meter, 'ai_tokens');

        $quota->admit($this->subject, KeySource::Application, 40, 't-1');
        self::assertSame(40, $this->used('ai_tokens'), 'The reserve is consumed before the task.');

        $quota->charge($this->subject, KeySource::Application, new Tokens(20, 5), $this->usd(0), 't-1', 40);
        self::assertSame(25, $this->used('ai_tokens'), 'What the task did not spend is given back.');

        $quota->admit($this->subject, KeySource::Application, 40, 't-2');
        $quota->charge($this->subject, KeySource::Application, new Tokens(50, 20), $this->usd(0), 't-2', 40);
        self::assertSame(95, $this->used('ai_tokens'), 'What the task spent past its reserve is recorded.');

        $quota->charge($this->subject, KeySource::Application, new Tokens(20, 5), $this->usd(0), 't-1', 40);
        $quota->charge($this->subject, KeySource::Application, new Tokens(50, 20), $this->usd(0), 't-2', 40);
        self::assertSame(95, $this->used('ai_tokens'), 'A charge replayed counts nothing more.');
        self::assertSame(65, $this->meter->refund($this->subject->value, 'ai_tokens', 't-2:adjust')->used, "The excess is recorded under the task's second key.");
        $this->meter->record($this->subject->value, 'ai_tokens', 30);

        $quota->admit($this->subject, KeySource::Application, 5, 't-3');
        $quota->charge($this->subject, KeySource::Application, new Tokens(3, 2), $this->usd(0), 't-3', 5);
        self::assertSame(100, $this->used('ai_tokens'), 'A task that spent its reserve exactly leaves it counted.');

        try {
            $quota->admit($this->subject, KeySource::Application, 1, 't-4');
            self::fail('A reserve past the quota was admitted.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(['ai_tokens', 1, 100], [$quotaExceeded->usage->meter, $quotaExceeded->requested, $quotaExceeded->usage->used]);
        }

        $quota->admit($this->subject, KeySource::User, 1, 't-5');
        $quota->charge($this->subject, KeySource::Application, new Tokens(30, 30), $this->usd(0), 't-6');
        self::assertSame(160, $this->used('ai_tokens'), 'Without a reserve, everything spent is recorded, over the limit if need be.');
        self::assertSame(0, $this->used('ai_tasks'));
    }

    public function testOneTaskIsConsumedBeforeEachStarts(): void
    {
        $quota = new Quota($this->meter, null, 'ai_tasks');

        $quota->admit($this->subject, KeySource::Application, 1_000, 't-1');
        $quota->admit($this->subject, KeySource::Application, 1_000, 't-1');
        $quota->admit($this->subject, KeySource::Application, 1_000, 't-2');
        $quota->charge($this->subject, KeySource::Application, new Tokens(500, 500), $this->usd(0), 't-2', 1_000);
        self::assertSame([2, 0], [$this->used('ai_tasks'), $this->used('ai_tokens')]);

        $this->expectException(QuotaExceeded::class);
        $quota->admit($this->subject, KeySource::Application, 1, 't-3');
    }

    public function testARefusalGivesBackWhatWasReservedBeforeIt(): void
    {
        $quota = new Quota($this->meter, 'ai_tokens', 'ai_tasks', budgetMeter: 'ai_budget', ceilingMeter: 'ai_ceiling');

        try {
            $quota->admit($this->subject, KeySource::Application, 10, 't-1', $this->usd(1001));
            self::fail('A cost bound past the budget was admitted.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(['ai_budget', 1001], [$quotaExceeded->usage->meter, $quotaExceeded->requested]);
        }

        self::assertSame([0, 0, 0, 0], $this->all(), 'The tokens reserved before the budget refused are given back.');

        $this->meter->record(Quota::APPLICATION_SUBJECT, 'ai_ceiling', 1000);

        try {
            $quota->admit($this->subject, KeySource::Application, 10, 't-2', $this->usd(501));
            self::fail('A cost bound past the ceiling was admitted.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame([Quota::APPLICATION_SUBJECT, 'ai_ceiling'], [$quotaExceeded->usage->subject, $quotaExceeded->usage->meter]);
        }

        self::assertSame([0, 0, 0, 1000], $this->all());

        $this->meter->record($this->subject->value, 'ai_tasks', 2);

        try {
            $quota->admit($this->subject, KeySource::Application, 10, 't-3', $this->usd(500));
            self::fail('A task past the meter of tasks was admitted.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame('ai_tasks', $quotaExceeded->usage->meter);
        }

        self::assertSame([0, 2, 0, 1000], $this->all(), 'Every meter reserved before the last refusal is given back.');

        $quota->release($this->subject, KeySource::Application, 't-3');
        self::assertSame([0, 2, 0, 1000], $this->all(), 'A refused task has nothing left to give back.');
    }

    public function testThePersonsOwnKeyIsNotCountedUnlessConfigured(): void
    {
        $quota = new Quota($this->meter, 'ai_tokens', 'ai_tasks', budgetMeter: 'ai_budget', ceilingMeter: 'ai_ceiling');

        $quota->admit($this->subject, KeySource::User, 1_000, 't-1', $this->usd(5000));
        $quota->charge($this->subject, KeySource::User, new Tokens(500, 500), $this->usd(50), 't-1', 1_000, $this->usd(5000));
        $quota->release($this->subject, KeySource::User, 't-1');
        self::assertSame([0, 0, 0, 0], $this->all());

        $counted = new Quota($this->meter, 'ai_tokens', 'ai_tasks', true, 'ai_budget', 'ai_ceiling');
        $counted->admit($this->subject, KeySource::User, 100, 't-2', $this->usd(30));
        self::assertSame([100, 1, 30, 30], $this->all());

        $counted->charge($this->subject, KeySource::User, new Tokens(5, 5), $this->usd(7), 't-2', 100, $this->usd(30));
        self::assertSame([10, 1, 7, 7], $this->all());
    }

    public function testAFailedCallGivesItsWholeReservationBack(): void
    {
        $quota = new Quota($this->meter, 'ai_tokens', 'ai_tasks', budgetMeter: 'ai_budget', ceilingMeter: 'ai_ceiling');
        $quota->admit($this->subject, KeySource::Application, 30, 't-1', $this->usd(200));
        $quota->admit($this->subject, KeySource::Application, 20, 't-2', $this->usd(100));
        self::assertSame([50, 2, 300, 300], $this->all());

        $quota->release($this->subject, KeySource::Application, 't-1');
        $quota->release($this->subject, KeySource::Application, 't-1');
        self::assertSame([20, 1, 100, 100], $this->all(), 'A release replayed gives nothing more back.');

        $quota->admit($this->subject, KeySource::Application, 1, 't-3');
        self::assertSame([21, 2, 100, 100], $this->all());

        $quota->release($this->subject, KeySource::User, 't-3');
        new Quota($this->meter, 'ai_tokens')->release($this->subject, KeySource::Application, 't-2');
        self::assertSame([1, 2, 100, 100], $this->all(), 'A key the quota does not count gives nothing back; a quota gives back only its meters.');

        new Quota($this->meter, null, 'ai_tasks', true)->release($this->subject, KeySource::User, 't-3');
        self::assertSame([1, 1, 100, 100], $this->all());
    }

    public function testTheBudgetReservesTheCostBoundAndIsSettledWithTheCost(): void
    {
        $quota = new Quota($this->meter, budgetMeter: 'ai_budget');

        $quota->admit($this->subject, KeySource::Application, 1_000_000, 't-1', $this->usd(600));
        self::assertSame(600, $this->used('ai_budget'));

        try {
            $quota->admit($this->subject, KeySource::Application, 1, 't-2', $this->usd(401));
            self::fail('A cost bound past the budget left was admitted.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(['ai_budget', 401, 600], [$quotaExceeded->usage->meter, $quotaExceeded->requested, $quotaExceeded->usage->used]);
        }

        $quota->charge($this->subject, KeySource::Application, new Tokens(10, 10), $this->usd(250), 't-1', 1_000_000, $this->usd(600));
        self::assertSame(250, $this->used('ai_budget'));

        $quota->admit($this->subject, KeySource::Application, 1, 't-2', $this->usd(400));
        $quota->charge($this->subject, KeySource::Application, Tokens::none(), $this->usd(749), 't-2', 1, $this->usd(400));
        $quota->charge($this->subject, KeySource::Application, Tokens::none(), $this->usd(749), 't-2', 1, $this->usd(400));
        self::assertSame([999, 0], [$this->used('ai_budget'), $this->used('ai_tokens')], 'A cost past its bound is recorded once.');

        $quota->admit($this->subject, KeySource::Application, 1, 't-3');
        $quota->admit($this->subject, KeySource::Application, 1, 't-4', $this->usd(0));
        self::assertSame(999, $this->used('ai_budget'), 'Without a bound, the budget only needs room left.');

        $quota->charge($this->subject, KeySource::Application, Tokens::none(), $this->usd(5), 't-3');
        self::assertSame(1004, $this->used('ai_budget'));

        try {
            $quota->admit($this->subject, KeySource::Application, 1, 't-5');
            self::fail('A task was admitted past its budget.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(['ai_budget', 1], [$quotaExceeded->usage->meter, $quotaExceeded->requested]);
        }
    }

    public function testTheCeilingCountsTheWholeApplicationOnItsSubject(): void
    {
        $quota = new Quota($this->meter, ceilingMeter: 'ai_ceiling', ceilingSubject: 'site');
        $other = Subject::fromString('project-2');

        $quota->admit($this->subject, KeySource::Application, 1, 't-1', $this->usd(1200));
        self::assertSame([1200, 0], [$this->meter->usage('site', 'ai_ceiling')->used, $this->used('ai_ceiling')]);

        try {
            $quota->admit($other, KeySource::Application, 1, 't-2', $this->usd(301));
            self::fail('A cost bound past the ceiling was admitted.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame(['site', 'ai_ceiling'], [$quotaExceeded->usage->subject, $quotaExceeded->usage->meter]);
        }

        $quota->charge($this->subject, KeySource::Application, Tokens::none(), $this->usd(1000), 't-1', 1, $this->usd(1200));
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

    public function testASemaphoreBoundsTheTasksOfASubjectRunningAtOnce(): void
    {
        $semaphores = new SemaphoreFactory(new LockStore(new LockFactory(new InMemoryStore())));
        $quota = new Quota($this->meter, semaphores: $semaphores, concurrency: 2);
        $other = Subject::fromString('project-2');

        $first = $quota->enter($this->subject, KeySource::Application);
        $second = $quota->enter($this->subject, KeySource::Application);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertTrue($first->isAcquired());
        self::assertNotNull($quota->enter($other, KeySource::Application), 'Another subject has places of its own.');
        self::assertNull($quota->enter($this->subject, KeySource::User), "The person's own key is not bounded unless counted.");

        try {
            $quota->enter($this->subject, KeySource::Application);
            self::fail('A third task ran at once.');
        } catch (TooManyTasks $tooManyTasks) {
            self::assertSame('The subject already runs 2 tasks at once, the most the quota allows.', $tooManyTasks->getMessage());
        }

        self::assertFalse($semaphores->createSemaphore('phil_ai.concurrency.'.hash('xxh128', 'project-1'), 2)->acquire(), 'The semaphore is named from a hash of the subject.');

        $first->release();
        self::assertNotNull($quota->enter($this->subject, KeySource::Application));
        self::assertNull(new Quota($this->meter)->enter($this->subject, KeySource::Application));
        self::assertNull(new Quota($this->meter, semaphores: $semaphores)->enter($this->subject, KeySource::Application));
        self::assertNotNull(new Quota($this->meter, countUserKeys: true, semaphores: $semaphores, concurrency: 1)->enter(Subject::fromString('project-3'), KeySource::User));
    }

    public function testABoundOfTasksAtOnceNeedsOneOrMoreAndASemaphoreFactoryAndIsKeptUntilReleased(): void
    {
        $semaphores = new SemaphoreFactory(new LockStore(new LockFactory(new InMemoryStore())));

        foreach ([[null, 2], [$semaphores, 0]] as [$factory, $concurrency]) {
            try {
                new Quota($this->meter, semaphores: $factory, concurrency: $concurrency);
                self::fail('A bound out of shape was accepted.');
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame(\sprintf('A bound of the tasks running at once is one or more, with a semaphore factory; got %d.', $concurrency), $invalidAiValue->getMessage());
            }
        }

        $quota = new Quota($this->meter, semaphores: $semaphores, concurrency: 1);
        self::assertNotNull($quota->enter($this->subject, KeySource::Application));

        $this->expectException(TooManyTasks::class);
        $quota->enter($this->subject, KeySource::Application);
    }

    /**
     * @phpstan-impure
     */
    private function used(string $meter): int
    {
        return $this->meter->usage($this->subject->value, $meter)->used;
    }

    /**
     * The tokens, tasks and budget of the subject, and the application's ceiling.
     *
     * @return list<int>
     *
     * @phpstan-impure
     */
    private function all(): array
    {
        return [$this->used('ai_tokens'), $this->used('ai_tasks'), $this->used('ai_budget'), $this->meter->usage(Quota::APPLICATION_SUBJECT, 'ai_ceiling')->used];
    }

    private function usd(int $micros): Cost
    {
        return new Cost($micros, 'USD');
    }
}
