<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Handler;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\Handler\AdvanceAccountDeletionHandler;
use Phil\PrivacyBundle\Application\Handler\ProcessDueAccountDeletionsHandler;
use Phil\PrivacyBundle\Application\Handler\RunRetentionHandler;
use Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion;
use Phil\PrivacyBundle\Application\Message\ProcessDueAccountDeletions;
use Phil\PrivacyBundle\Application\Message\RunRetention;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryAccountDeletions;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryExportArchive;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingPurger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(AdvanceAccountDeletionHandler::class)]
#[CoversClass(ProcessDueAccountDeletionsHandler::class)]
#[CoversClass(RunRetentionHandler::class)]
#[CoversClass(AdvanceAccountDeletion::class)]
#[CoversClass(ProcessDueAccountDeletions::class)]
#[CoversClass(RunRetention::class)]
final class HandlersTest extends TestCase
{
    public function testTheSweepSendsOneMessagePerDueRequestAndRemovesExpiredExports(): void
    {
        $clock = new MockClock('2026-01-01T00:00:00Z');
        $archive = new MemoryExportArchive();
        $archive->put('old', 'x', new DateTimeImmutable('2025-01-01T00:00:00Z'));

        $deletions = $this->deletions(new MemoryAccountDeletions(), $archive, $clock);
        $request = $deletions->request(DataSubject::fromString('m1'));
        $clock->modify('+30 days');

        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $sent = [];

            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->sent[] = $message;

                return new Envelope($message);
            }
        };

        new ProcessDueAccountDeletionsHandler($deletions, $bus)();

        self::assertCount(1, $bus->sent);
        self::assertInstanceOf(AdvanceAccountDeletion::class, $bus->sent[0]);
        self::assertSame($request->id, $bus->sent[0]->deletionId);
        self::assertSame([], $archive->files);
    }

    public function testAMessageCarriesAnIdentifierAndNothingElse(): void
    {
        self::assertSame(['deletionId'], array_keys(get_object_vars(new AdvanceAccountDeletion('x'))));
        self::assertSame([], get_object_vars(new ProcessDueAccountDeletions()));
        self::assertSame([], get_object_vars(new RunRetention()));
    }

    public function testAMessageAdvancesItsRequest(): void
    {
        $clock = new MockClock('2026-01-01T00:00:00Z');
        $store = new MemoryAccountDeletions();
        $deletions = $this->deletions($store, new MemoryExportArchive(), $clock);
        $request = $deletions->request(DataSubject::fromString('m1'));
        $clock->modify('+30 days');

        new AdvanceAccountDeletionHandler($deletions)(new AdvanceAccountDeletion($request->id));

        self::assertSame(DeletionPlace::Erased, $store->rows[$request->id]->getPlace());
    }

    public function testTheRetentionMessageRunsTheJob(): void
    {
        $purger = new RecordingPurger('trash');
        $run = new RetentionRun(new FixedScheduleSource(new RetentionSchedule([new RetentionRule('R-1', 'trash', [], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned)])), [$purger]);

        new RunRetentionHandler(new RetentionJob($run, new LockFactory(new InMemoryStore()), new MockClock()))();

        self::assertCount(1, $purger->orders);
    }

    private function deletions(MemoryAccountDeletions $store, MemoryExportArchive $archive, MockClock $clock): AccountDeletions
    {
        return new AccountDeletions($store, DeletionWorkflow::create(), new LockFactory(new InMemoryStore()), new SubjectExport([]), new SubjectErasure([]), $archive, $clock, 'P30D', 'P7D');
    }
}
