<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Application\RunReport;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;
use Phil\ScheduledTransitionsBundle\Domain\Transition\TransitionStatus;
use Phil\ScheduledTransitionsBundle\Infrastructure\Doctrine\DoctrineScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\Clock;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\Database;
use Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures\RecordingHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\NativeClock;

#[CoversClass(Scheduler::class)]
#[CoversClass(RunReport::class)]
#[CoversClass(DoctrineScheduledTransitionRepository::class)]
final class SchedulerTest extends TestCase
{
    private EntityManager $entityManager;

    private RecordingHandler $handler;

    private Clock $clock;

    private Scheduler $scheduler;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->handler = new RecordingHandler();
        $this->clock = new Clock(new DateTimeImmutable('2026-11-09 12:00 UTC'));
        $this->scheduler = new Scheduler(new DoctrineScheduledTransitionRepository($this->entityManager), [$this->handler], $this->clock, 2);
    }

    public function testATransitionIsAppliedOnceDue(): void
    {
        $page = ResourceReference::of('page', '42');
        $publish = $this->scheduler->schedule($page, 'publish', new DateTimeImmutable('2026-11-09 14:00 UTC'));
        $unpublish = $this->scheduler->schedule($page, 'unpublish', new DateTimeImmutable('2026-12-09 14:00 UTC'));
        self::assertSame($publish, $this->scheduler->schedule($page, 'publish', new DateTimeImmutable('2026-11-09 13:00 UTC')));
        $this->entityManager->clear();

        self::assertSame(['publish', 'unpublish'], array_map(static fn (ScheduledTransition $pending): string => $pending->transition(), $this->scheduler->pendingFor($page)));
        self::assertSame('2026-11-09T13:00:00+00:00', $this->scheduler->pendingFor($page)[0]->dueAt()->format('c'));
        self::assertEquals(new RunReport(0, 0), $this->scheduler->runDue());

        $this->clock->now = new DateTimeImmutable('2026-11-09 13:00 UTC');
        self::assertEquals(new RunReport(1, 0), $this->scheduler->runDue());
        self::assertSame(['publish page:42'], $this->handler->applied);
        $this->entityManager->clear();
        $stored = $this->entityManager->find(ScheduledTransition::class, $publish->id);
        self::assertSame([TransitionStatus::Done, '2026-11-09T13:00:00+00:00'], [$stored?->status(), $stored?->appliedAt()?->format('c')]);
        self::assertSame([$unpublish->id], array_map(static fn (ScheduledTransition $pending): string => $pending->id, $this->scheduler->pendingFor($page)));
        self::assertEquals(new RunReport(0, 0), $this->scheduler->runDue());
    }

    public function testAFailureIsRetriedThenGivenUp(): void
    {
        $this->handler->failing = ['1'];
        $locked = $this->scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        $this->scheduler->schedule(ResourceReference::of('post', '2'), 'publish', new DateTimeImmutable('2026-11-09 11:30 UTC'));
        $this->scheduler->schedule(ResourceReference::of('page', '3'), 'publish', new DateTimeImmutable('2026-11-09 11:45 UTC'));

        self::assertEquals(new RunReport(0, 2), $this->scheduler->runDue(2));
        self::assertEquals(new RunReport(1, 2), $this->scheduler->runDue());
        $this->entityManager->clear();
        $stored = $this->entityManager->find(ScheduledTransition::class, $locked->id);
        self::assertSame([TransitionStatus::Failed, 2, 'The page 1 is locked.'], [$stored?->status(), $stored?->attempts(), $stored?->lastError()]);
        self::assertSame(['publish page:3'], $this->handler->applied);

        $post = $this->entityManager->getRepository(ScheduledTransition::class)->findOneBy(['resourceKind' => 'post']);
        self::assertSame([TransitionStatus::Failed, 'No handler applies "publish" to post:2: register a TransitionHandler for it.'], [$post?->status(), $post?->lastError()]);
    }

    public function testTransitionsAreCancelled(): void
    {
        $page = ResourceReference::of('page', '42');
        $this->scheduler->schedule($page, 'publish', new DateTimeImmutable('2026-11-10 UTC'));
        $this->scheduler->schedule($page, 'unpublish', new DateTimeImmutable('2026-11-11 UTC'));
        $this->scheduler->schedule($page, 'archive', new DateTimeImmutable('2026-11-12 UTC'));

        self::assertSame(1, $this->scheduler->cancel($page, 'unpublish'));
        $this->entityManager->clear();
        self::assertSame(['publish', 'archive'], array_map(static fn (ScheduledTransition $pending): string => $pending->transition(), $this->scheduler->pendingFor($page)));
        self::assertSame(2, $this->scheduler->cancel($page));
        $this->entityManager->clear();
        self::assertSame([], $this->scheduler->pendingFor($page));
        self::assertSame(0, $this->scheduler->cancel($page));

        $this->scheduler->schedule(ResourceReference::of('post', '42'), 'publish', new DateTimeImmutable('2026-11-10 UTC'));
        self::assertSame(0, $this->scheduler->cancel($page));
        self::assertCount(1, $this->scheduler->pendingFor(ResourceReference::of('post', '42')));
    }

    public function testTheSoonestComesFirst(): void
    {
        $page = ResourceReference::of('page', '7');
        $this->scheduler->schedule($page, 'archive', new DateTimeImmutable('2026-12-01 UTC'));
        $this->scheduler->schedule($page, 'publish', new DateTimeImmutable('2026-11-10 UTC'));

        $this->entityManager->clear();

        self::assertSame(['publish', 'archive'], array_map(static fn (ScheduledTransition $pending): string => $pending->transition(), $this->scheduler->pendingFor($page)));
    }

    public function testWithoutAClockItIsNow(): void
    {
        $scheduler = new Scheduler(new DoctrineScheduledTransitionRepository($this->entityManager), [$this->handler], new NativeClock());
        $scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('-1 minute'));
        $scheduler->schedule(ResourceReference::of('page', '2'), 'publish', new DateTimeImmutable('+1 hour'));

        $this->handler->failing = ['1'];

        self::assertEquals(new RunReport(0, 1), $scheduler->runDue());
        self::assertEquals(new RunReport(0, 1), $scheduler->runDue());
        self::assertEquals(new RunReport(0, 1), $scheduler->runDue());
        self::assertEquals(new RunReport(0, 0), $scheduler->runDue());
        self::assertEquals(new RunReport(), new RunReport(0, 0));
    }

    public function testABatchIsAtMostSoMany(): void
    {
        foreach (range(1, 102) as $n) {
            $this->scheduler->schedule(ResourceReference::of('page', (string) $n), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        }

        self::assertEquals(new RunReport(100, 0), $this->scheduler->runDue());
        self::assertEquals(new RunReport(2, 0), $this->scheduler->runDue());
    }

    public function testTwoRunsAtOnceNeverApplyTheSameTransition(): void
    {
        $repository = new DoctrineScheduledTransitionRepository($this->entityManager);
        $other = new Scheduler($repository, [$this->handler], $this->clock, 2);
        $first = $this->scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        $this->scheduler->schedule(ResourceReference::of('page', '2'), 'publish', new DateTimeImmutable('2026-11-09 11:30 UTC'));
        $this->scheduler->schedule(ResourceReference::of('page', '3'), 'publish', new DateTimeImmutable('2026-11-09 11:45 UTC'));

        $reports = [];
        // While the first run applies page 1, another run of one transition starts: it finds page 1 claimed, and
        // applies page 2 alone. The first run then skips page 2, already applied, and goes on with page 3.
        $this->handler->during = static function (string $applied) use ($other, &$reports): void {
            if ('publish page:1' === $applied) {
                $reports[] = $other->runDue(1);
            }
        };

        self::assertEquals(new RunReport(2, 0), $this->scheduler->runDue());
        self::assertEquals([new RunReport(1, 0)], $reports);
        self::assertSame(['publish page:2', 'publish page:1', 'publish page:3'], $this->handler->applied);

        $this->entityManager->clear();
        self::assertFalse($repository->claim($this->entityManager->find(ScheduledTransition::class, $first->id) ?? self::fail('gone'), $this->clock->now, $this->clock->now->modify('+1 minute')));
    }

    public function testAClaimRunsFromTheMomentItIsMadeInALongRun(): void
    {
        $this->scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        $second = $this->scheduler->schedule(ResourceReference::of('page', '2'), 'publish', new DateTimeImmutable('2026-11-09 11:30 UTC'));
        $connection = $this->entityManager->getConnection();
        $claims = [];
        // Page 1 takes longer than a claim lasts: page 2 is still claimed for ten minutes from when its turn comes.
        $this->handler->during = function (string $applied) use ($connection, $second, &$claims): void {
            if ('publish page:1' === $applied) {
                $this->clock->now = $this->clock->now->modify('+700 seconds');
            } else {
                $claims[] = $connection->fetchOne('SELECT claimed_until FROM phil_scheduled_transition WHERE id = ?', [$second->id]);
            }
        };

        self::assertEquals(new RunReport(2, 0), $this->scheduler->runDue());
        self::assertSame(['2026-11-09 12:21:40'], $claims);
    }

    public function testAClaimLapsesAndAFailedAttemptLetsGoAtOnce(): void
    {
        $repository = new DoctrineScheduledTransitionRepository($this->entityManager);
        $page = $this->scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        $now = $this->clock->now;

        self::assertTrue($repository->claim($page, $now, $now->modify('+10 minutes')));
        self::assertFalse($repository->claim($page, $now, $now->modify('+10 minutes')));
        self::assertSame([], $repository->due($now->modify('+9 minutes'), 10));
        self::assertCount(1, $repository->due($now->modify('+11 minutes'), 10));
        self::assertTrue($repository->claim($page, $now->modify('+11 minutes'), $now->modify('+21 minutes')));
        $this->entityManager->clear();
        self::assertSame('2026-11-09T12:21:00+00:00', $this->entityManager->find(ScheduledTransition::class, $page->id)?->claimedUntil()?->format('c'));
        self::assertNull(ScheduledTransition::of(ResourceReference::of('page', '9'), 'publish', $now)->claimedUntil());
        $this->entityManager->find(ScheduledTransition::class, $page->id) ?? self::fail('gone');

        $this->handler->failing = ['2'];
        $this->scheduler->schedule(ResourceReference::of('page', '2'), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        self::assertEquals(new RunReport(0, 1), $this->scheduler->runDue());
        self::assertSame(['page:2'], array_map(static fn (ScheduledTransition $due): string => $due->resource()->toString(), $repository->due($now, 10)), 'a failed attempt is retried at the next run');
    }

    /**
     * Each outcome is one `UPDATE`, and the transition leaves the unit of work once recorded: the handler of the next
     * transition, and its flush, no longer see it.
     */
    public function testARecordedTransitionIsWrittenOnceAndLeavesTheUnitOfWork(): void
    {
        $this->handler->failing = ['2'];
        $ids = array_map(fn (int $page): string => $this->scheduler->schedule(ResourceReference::of('page', (string) $page), 'publish', new DateTimeImmutable(\sprintf('2026-11-09 11:%02d UTC', $page)))->id, [1, 2, 3]);
        $this->entityManager->clear();
        $managed = [];
        $this->handler->during = function () use (&$managed): void {
            $managed[] = \count($this->entityManager->getUnitOfWork()->getIdentityMap()[ScheduledTransition::class] ?? []);
            $this->entityManager->flush();
        };

        self::assertEquals(new RunReport(2, 1), $this->scheduler->runDue());

        self::assertSame([3, 1], $managed, 'The failed attempt throws before the callback; the first and second are detached by then.');
        self::assertSame([], $this->entityManager->getUnitOfWork()->getIdentityMap()[ScheduledTransition::class] ?? []);
        self::assertSame([
            ['id' => $ids[0], 'status' => 'done', 'attempts' => 1, 'last_error' => null, 'applied_at' => '2026-11-09 12:00:00', 'claimed_until' => null],
            ['id' => $ids[1], 'status' => 'pending', 'attempts' => 1, 'last_error' => 'The page 2 is locked.', 'applied_at' => null, 'claimed_until' => null],
            ['id' => $ids[2], 'status' => 'done', 'attempts' => 1, 'last_error' => null, 'applied_at' => '2026-11-09 12:00:00', 'claimed_until' => null],
        ], $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, status, attempts, last_error, applied_at, claimed_until FROM phil_scheduled_transition ORDER BY due_at'));
    }

    public function testAFailureThatClosesTheEntityManagerIsStillCounted(): void
    {
        $closing = $this->scheduler->schedule(ResourceReference::of('page', '1'), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        $next = $this->scheduler->schedule(ResourceReference::of('page', '2'), 'publish', new DateTimeImmutable('2026-11-09 11:30 UTC'));
        $this->handler->during = function (string $applied): void {
            if ('publish page:1' === $applied) {
                // As a failed flush does: the entity manager is closed, and the error goes up.
                $this->entityManager->close();

                throw new RuntimeException('A constraint was violated.');
            }
        };

        self::assertEquals(new RunReport(0, 1), $this->scheduler->runDue());

        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, status, attempts, last_error, claimed_until FROM phil_scheduled_transition ORDER BY due_at');
        self::assertSame([
            ['id' => $closing->id, 'status' => 'pending', 'attempts' => 1, 'last_error' => 'A constraint was violated.', 'claimed_until' => null],
            ['id' => $next->id, 'status' => 'pending', 'attempts' => 0, 'last_error' => null, 'claimed_until' => null],
        ], $rows, 'the attempt is saved, and the run stops before the next transition');

        $entityManager = Database::entityManager();
        $applied = new Scheduler(new DoctrineScheduledTransitionRepository($entityManager), [new RecordingHandler()], new Clock(new DateTimeImmutable('2026-11-09 12:00 UTC')));
        $entityManager->getConnection()->executeStatement('DELETE FROM phil_scheduled_transition');
        $done = ScheduledTransition::of(ResourceReference::of('page', '3'), 'publish', new DateTimeImmutable('2026-11-09 11:00 UTC'));
        $done->applied(new DateTimeImmutable('2026-11-09 12:00 Europe/Paris'));

        $entityManager->persist($done);
        $entityManager->flush();
        $entityManager->close();
        new DoctrineScheduledTransitionRepository($entityManager)->record($done);
        self::assertSame(['status' => 'done', 'applied_at' => '2026-11-09 11:00:00'], $entityManager->getConnection()->fetchAssociative('SELECT status, applied_at FROM phil_scheduled_transition'));
        self::assertEquals(new RunReport(0, 0), $applied->runDue());
    }
}
