<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\AccountDeletion;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\Exception\AccountDeletionBusy;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryAccountDeletions;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryExportArchive;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\StaleFirstRead;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(AccountDeletions::class)]
#[CoversClass(DeletionWorkflow::class)]
#[CoversClass(AccountDeletionBusy::class)]
final class AccountDeletionsTest extends TestCase
{
    private MemoryAccountDeletions $store;

    private MemoryExportArchive $archive;

    private MockClock $clock;

    private LockFactory $locks;

    private RecordingEraser $eraser;

    private TestHandler $log;

    private DataSubject $subject;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new MemoryAccountDeletions();
        $this->archive = new MemoryExportArchive();
        $this->clock = new MockClock('2026-01-01T00:00:00Z');
        $this->locks = new LockFactory(new InMemoryStore());
        $this->eraser = new RecordingEraser('members');
        $this->log = new TestHandler();
        $this->subject = DataSubject::fromString('member-1');
    }

    public function testARequestStartsTheDelay(): void
    {
        $deletion = $this->service()->request($this->subject);

        self::assertSame(DeletionPlace::Requested, $deletion->getPlace());
        self::assertSame('2026-01-01', $deletion->requestedAt->format('Y-m-d'));
        self::assertSame('2026-01-31', $deletion->effectiveAt->format('Y-m-d'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $deletion->id);
        self::assertTrue($this->store->rows[$deletion->id]->isOpen());
        self::assertSame(0, $this->eraser->calls);
        self::assertSame([], $this->archive->files);
        $this->assertLogsNeverNameThePerson();
    }

    public function testAnOpenRequestIsAnswered(): void
    {
        $first = $this->service()->request($this->subject);

        self::assertSame($first->id, $this->service()->request($this->subject)->id);
        self::assertCount(1, $this->store->rows);
    }

    public function testAClosedRequestDoesNotPreventANewOne(): void
    {
        $service = $this->service();
        $first = $service->request($this->subject);
        $service->cancel($this->subject);

        self::assertNotSame($first->id, $service->request($this->subject)->id);
    }

    public function testNothingIsDoneBeforeTheDelayIsOver(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $this->clock->modify('+29 days');

        self::assertSame([], $service->dueIdentifiers(10));
        self::assertSame(DeletionPlace::Requested, $service->advance($deletion->id));
        self::assertSame(0, $this->eraser->calls);
    }

    public function testAfterTheDelayThePersonIsExportedThenErased(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');

        self::assertSame([$deletion->id], $service->dueIdentifiers(10));
        self::assertSame(DeletionPlace::Erased, $service->advance($deletion->id));

        $row = $this->store->rows[$deletion->id];
        self::assertSame(DeletionPlace::Erased, $row->getPlace());
        self::assertSame($deletion->id, $row->exportReference());
        self::assertSame('2026-01-31', $row->closedAt()?->format('Y-m-d'));
        self::assertSame(1, $this->eraser->calls);
        self::assertSame('2026-02-07', $this->archive->files[$deletion->id][1]->format('Y-m-d'));
        self::assertSame(['members' => ['subject' => 'member-1']], json_decode($this->archive->files[$deletion->id][0], true, 512, \JSON_THROW_ON_ERROR));
        self::assertTrue($this->log->hasInfoThatContains('Account deletion {id} exported.'));
        self::assertTrue($this->log->hasInfoThatContains('Account deletion {id} erased.'));
        self::assertSame(2, $this->store->saves - 1);
        $this->assertLogsNeverNameThePerson();
    }

    public function testTheExportIsReadableAndPretty(): void
    {
        $service = $this->service(exportData: ['url' => 'https://example.org/a', 'name' => 'Éloïse']);
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $service->advance($deletion->id);

        $content = $this->archive->files[$deletion->id][0];
        self::assertStringContainsString("\n", $content);
        self::assertStringContainsString('Éloïse', $content);
        self::assertStringContainsString('https://example.org/a', $content);
    }

    public function testTheWorkflowEventsReachTheApplicationBeforeTheErasure(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        $dispatcher->addListener('workflow.phil_privacy_account_deletion.completed.export', function () use (&$seen): void {
            $seen[] = $this->eraser->calls;
        });
        $service = $this->service(dispatcher: $dispatcher);
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $service->advance($deletion->id);

        self::assertSame([0], $seen);
    }

    public function testAnIncompleteErasureStaysAtExportedAndIsResumedWithoutExportingAgain(): void
    {
        $failing = $this->service(eraser: new RecordingEraser('members', fails: true));
        $deletion = $failing->request($this->subject);
        $this->clock->modify('+30 days');

        try {
            $failing->advance($deletion->id);
            self::fail('An incomplete erasure must not pass.');
        } catch (ErasureIncomplete) {
            self::assertSame(DeletionPlace::Exported, $this->store->rows[$deletion->id]->getPlace());
            self::assertNull($this->store->rows[$deletion->id]->closedAt());
        }

        self::assertTrue($this->log->hasWarningThatContains('the erasure failed in {modules}'));
        $last = array_last($this->log->getRecords());
        self::assertNotNull($last);
        self::assertSame(['id' => $deletion->id, 'modules' => ['members']], $last->context);
        $this->assertLogsNeverNameThePerson();

        $this->archive->files[$deletion->id][0] = 'first export';
        self::assertSame([$deletion->id], $failing->dueIdentifiers(10));
        self::assertSame(DeletionPlace::Erased, $this->service()->advance($deletion->id));
        self::assertSame('first export', $this->archive->files[$deletion->id][0]);
        self::assertSame(1, $this->eraser->calls);
    }

    public function testACancellationDuringTheDelayKeepsEverything(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $this->clock->modify('+1 day');

        self::assertTrue($service->cancel($this->subject));

        $row = $this->store->rows[$deletion->id];
        self::assertSame(DeletionPlace::Cancelled, $row->getPlace());
        self::assertSame('2026-01-02', $row->closedAt()?->format('Y-m-d'));
        self::assertNull($service->openFor($this->subject));
        $this->clock->modify('+60 days');
        self::assertSame([], $service->dueIdentifiers(10));
        self::assertSame(DeletionPlace::Cancelled, $service->advance($deletion->id));
        self::assertSame(0, $this->eraser->calls);
        self::assertTrue($this->log->hasInfoThatContains('Account deletion {id} cancelled.'));
        $this->assertLogsNeverNameThePerson();
    }

    public function testNoCancellationOnceTheDelayIsOver(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');

        self::assertFalse($service->cancel($this->subject));
        self::assertSame(DeletionPlace::Requested, $this->store->rows[$deletion->id]->getPlace());
        self::assertNull($this->store->rows[$deletion->id]->closedAt());
    }

    public function testNothingToCancelForSomeoneWithoutARequest(): void
    {
        self::assertFalse($this->service()->cancel($this->subject));
    }

    public function testAnUnknownRequestAdvancesNowhere(): void
    {
        self::assertNull($this->service()->advance('0123456789abcdef0123456789abcdef'));
    }

    public function testTheLockIsReleasedAfterEveryStage(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $service->cancel($this->subject);
        $next = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $service->advance($next->id);
        $service->advance($deletion->id);

        self::assertTrue($this->held()->acquire());
    }

    public function testTheLockIsReleasedWhenTheErasureFails(): void
    {
        $failing = $this->service(eraser: new RecordingEraser('members', fails: true));
        $deletion = $failing->request($this->subject);
        $this->clock->modify('+30 days');

        try {
            $failing->advance($deletion->id);
            self::fail('An incomplete erasure must not pass.');
        } catch (ErasureIncomplete $erasureIncomplete) {
            // The exception is kept, with the frames that took the lock as an argument: only the release lets it go.
            self::assertTrue($this->held()->acquire());
            self::assertSame(['members'], array_keys($erasureIncomplete->failures));
        }
    }

    public function testTheLockIsReleasedWhenTheStoreFailsDuringARequestOrACancellation(): void
    {
        $service = $this->service();
        $this->store->failing = true;

        try {
            $service->request($this->subject);
            self::fail('A request the store cannot save must not pass.');
        } catch (RuntimeException $runtimeException) {
            self::assertTrue($this->held()->acquire());
            self::assertSame('The store is down.', $runtimeException->getMessage());
        }

        $this->store->failing = false;
        $service->request($this->subject);
        $this->store->failing = true;

        try {
            $service->cancel($this->subject);
            self::fail('A cancellation the store cannot save must not pass.');
        } catch (RuntimeException $runtimeException) {
            self::assertTrue($this->held()->acquire());
            self::assertSame('The store is down.', $runtimeException->getMessage());
        }
    }

    public function testEachStageLogsTheIdentifierOfTheRequestAndNothingElse(): void
    {
        $service = $this->service();
        $cancelled = $service->request($this->subject);
        $service->cancel($this->subject);
        $erased = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $service->advance($erased->id);

        self::assertSame([
            ['Account deletion {id} requested.', ['id' => $cancelled->id]],
            ['Account deletion {id} cancelled.', ['id' => $cancelled->id]],
            ['Account deletion {id} requested.', ['id' => $erased->id]],
            ['Account deletion {id} exported.', ['id' => $erased->id]],
            ['Account deletion {id} erased.', ['id' => $erased->id]],
        ], array_map(static fn (LogRecord $record): array => [$record->message, $record->context], $this->log->getRecords()));
    }

    public function testARequestRemovedBeforeTheLockIsHeldAdvancesNowhere(): void
    {
        $deletion = $this->service()->request($this->subject);
        $this->clock->modify('+30 days');
        $removed = new AccountDeletions(new StaleFirstRead($this->store, $deletion->id, removed: true), DeletionWorkflow::create(), $this->locks, new SubjectExport([]), new SubjectErasure([$this->eraser]), $this->archive, $this->clock, 'P30D', 'P7D');

        self::assertNull($removed->advance($deletion->id));
        self::assertSame(0, $this->eraser->calls);
    }

    public function testTheWorkflowDeclaresItsPlacesInTheOrderOfTheStages(): void
    {
        $definition = DeletionWorkflow::definition();

        self::assertSame(['requested', 'exported', 'erased', 'cancelled'], array_values($definition->getPlaces()));
        self::assertSame(['requested'], $definition->getInitialPlaces());
    }

    public function testARequestOrACancellationWhileAnotherProcessHoldsThePersonIsRefused(): void
    {
        $service = $this->service();
        $held = $this->held();
        self::assertTrue($held->acquire());

        foreach (['request' => $service->request(...), 'cancel' => $service->cancel(...)] as $action => $call) {
            try {
                $call($this->subject);
                self::fail($action.' must not pass.');
            } catch (AccountDeletionBusy $accountDeletionBusy) {
                self::assertSame('An account deletion of this person is being processed.', $accountDeletionBusy->getMessage());
            }
        }

        self::assertSame([], $this->store->rows);
    }

    public function testOneWorkerAtATimeForAPerson(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $held = $this->held();
        $held->acquire();

        self::assertSame(DeletionPlace::Requested, $service->advance($deletion->id));
        self::assertSame(0, $this->eraser->calls);
        $held->release();
        self::assertSame(DeletionPlace::Erased, $service->advance($deletion->id));
    }

    public function testTheRequestIsReadAgainOnceTheLockIsHeld(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $stale = new StaleFirstRead($this->store, $deletion->id);
        $reread = new AccountDeletions($stale, DeletionWorkflow::create(), $this->locks, new SubjectExport([new FixedExporter('members', [])]), new SubjectErasure([$this->eraser]), $this->archive, $this->clock, 'P30D', 'P7D');

        self::assertSame(DeletionPlace::Cancelled, $reread->advance($deletion->id));
        self::assertSame(0, $this->eraser->calls);
    }

    public function testTheLockIsLongEnoughAndRefreshedBetweenStages(): void
    {
        $store = new RecordingLockStore();
        $service = new AccountDeletions($this->store, DeletionWorkflow::create(), new LockFactory($store), new SubjectExport([]), new SubjectErasure([]), $this->archive, $this->clock, 'P30D', 'P7D');
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $service->advance($deletion->id);

        self::assertSame([900.0, 900.0, 900.0], $store->lifetimes);
    }

    public function testExpiredExportsArePurged(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);
        $this->clock->modify('+30 days');
        $service->advance($deletion->id);
        $this->clock->modify('+6 days');
        self::assertSame(0, $service->purgeExpiredExports());
        $this->clock->modify('+1 day');
        self::assertSame(1, $service->purgeExpiredExports());
    }

    public function testDueRequestsComeInBatches(): void
    {
        $service = $this->service();
        $service->request($this->subject);
        $service->request(DataSubject::fromString('member-2'));

        $this->clock->modify('+30 days');

        self::assertCount(1, $service->dueIdentifiers(1));
    }

    public function testTheOpenRequestOfAPersonCanBeRead(): void
    {
        $service = $this->service();
        $deletion = $service->request($this->subject);

        self::assertInstanceOf(AccountDeletion::class, $service->openFor($this->subject));
        self::assertSame($deletion->id, $service->openFor($this->subject)->id);
    }

    /**
     * @param array<string, mixed> $exportData
     */
    private function service(?RecordingEraser $eraser = null, array $exportData = [], ?EventDispatcher $dispatcher = null): AccountDeletions
    {
        return new AccountDeletions(
            $this->store,
            DeletionWorkflow::create($dispatcher),
            $this->locks,
            new SubjectExport([new FixedExporter('members', $exportData)]),
            new SubjectErasure([$eraser ?? $this->eraser]),
            $this->archive,
            $this->clock,
            'P30D',
            'P7D',
            new Logger('privacy', [$this->log]),
        );
    }

    private function held(): LockInterface
    {
        return $this->locks->createLock('phil_privacy.account_deletion.'.hash('xxh128', 'member-1'));
    }

    private function assertLogsNeverNameThePerson(): void
    {
        foreach ($this->log->getRecords() as $record) {
            self::assertStringNotContainsString('member-1', $record->message.json_encode($record->context, \JSON_THROW_ON_ERROR));
        }
    }
}
