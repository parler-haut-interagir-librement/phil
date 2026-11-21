<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Erasure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\Outbox\Codecs;
use Phil\Outbox\Exception\UnknownEventType;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\StoreOutbox;
use Phil\PrivacyBundle\Application\Contract\ExternalPersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Erasure\ErasureRequests;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\Exception\ModuleErasureFailed;
use Phil\PrivacyBundle\Application\GuestRequest\EmailGuestSubjects;
use Phil\PrivacyBundle\Application\Handler\EraseModuleHandler;
use Phil\PrivacyBundle\Application\Message\EraseModule;
use Phil\PrivacyBundle\Domain\Erasure\AttentionReason;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Erasure\StepState;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\PrivacyBundle\Infrastructure\Outbox\OutboxErasureMessages;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ContextLogger;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ErasureWorkbench;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryGuestRequests;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingExternalEraser;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\SubjectRecordingEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErasureRequests::class)]
#[CoversClass(EraseModuleHandler::class)]
#[CoversClass(ModuleErasureFailed::class)]
#[CoversClass(EraseModule::class)]
#[CoversClass(OutboxErasureMessages::class)]
final class ErasureRequestsTest extends TestCase
{
    private ErasureWorkbench $bench;

    private RecordingEraser $billing;

    private RecordingEraser $forum;

    private RecordingExternalEraser $mailing;

    private MemoryGuestRequests $guests;

    private ContextLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->bench = new ErasureWorkbench();
        $this->billing = new RecordingEraser('billing');
        $this->forum = new RecordingEraser('forum');
        $this->mailing = new RecordingExternalEraser();
        $this->guests = new MemoryGuestRequests();
        $this->logger = new ContextLogger();
    }

    public function testARequestErasesEveryModuleOfTheInventoryAndKeepsItsVersion(): void
    {
        $requests = $this->requests();

        $request = $requests->open(DataSubject::fromString('member-7'));

        self::assertMatchesRegularExpression(ErasureRequest::ID_PATTERN, $request->id);
        self::assertSame($requests->inventory()->version, $request->inventoryVersion);
        self::assertSame(RequestState::Running, $request->state);
        self::assertSame(['billing', 'forum', 'mailing'], $this->modules($request->id));
        self::assertSame(StepState::Pending, $this->bench->store->step($request->id, 'forum')?->state);
        self::assertSame(['billing' => false, 'forum' => false, 'mailing' => true], $requests->inventory()->modules);
    }

    public function testOneOutboxRecordPerModuleIsWrittenWithTheRequest(): void
    {
        $request = $this->requests()->open(DataSubject::fromString('member-7'));

        self::assertSame([
            [EraseModule::TYPE, '{"request":"'.$request->id.'","module":"billing"}', $request->id.':billing', null],
            [EraseModule::TYPE, '{"request":"'.$request->id.'","module":"forum"}', $request->id.':forum', null],
            [EraseModule::TYPE, '{"request":"'.$request->id.'","module":"mailing"}', $request->id.':mailing', null],
        ], array_map(static fn (OutboxRecord $record): array => [$record->type, $record->payloadJson, $record->idempotencyKey, $record->orderingKey], $this->bench->pending()));
    }

    public function testTheModulesNamedAreTheOnlyOnesErased(): void
    {
        $request = $this->requests()->open(DataSubject::fromString('member-7'), ['forum', 'billing', 'forum']);

        self::assertSame(['billing', 'forum'], $this->modules($request->id));
        self::assertCount(2, $this->bench->pending());
    }

    public function testAnUnknownModuleIsRefusedAndNothingIsWritten(): void
    {
        try {
            $this->requests()->open(DataSubject::fromString('member-7'), ['forum', 'crm']);
            self::fail('An unknown module was accepted.');
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            self::assertSame('"crm" is not a module of the erasure inventory.', $invalidPrivacyValue->getMessage());
        }

        self::assertEquals(0, $this->bench->connection->fetchOne('SELECT COUNT(*) FROM phil_privacy_erasure_request'));
        self::assertSame([], $this->bench->pending());
    }

    public function testAnOutboxThatFailsRollsTheRequestBack(): void
    {
        $broken = new ErasureRequests($this->bench->store, new OutboxErasureMessages(new StoreOutbox($this->bench->records, new Codecs([]), $this->bench->clock)), [$this->billing], [], $this->bench->clock);

        try {
            $broken->open(DataSubject::fromString('member-7'));
            self::fail('A request was opened without its outbox records.');
        } catch (UnknownEventType) {
        }

        self::assertEquals(0, $this->bench->connection->fetchOne('SELECT COUNT(*) FROM phil_privacy_erasure_request'));
        self::assertEquals(0, $this->bench->connection->fetchOne('SELECT COUNT(*) FROM phil_privacy_erasure_step'));
    }

    public function testARequestOpenedAgainUnderItsIdentifierIsTheSameOne(): void
    {
        $requests = $this->requests();
        $id = str_repeat('ab', 16);

        $first = $requests->open(DataSubject::fromString('member-7'), null, $id);
        $this->bench->clock->sleep(60);
        $again = $requests->open(DataSubject::fromString('member-7'), ['forum'], $id);

        self::assertSame($id, $first->id);
        self::assertEquals($first, $again);
        self::assertCount(3, $this->bench->pending());
    }

    public function testEachModuleRunsOnceAndTheRequestCompletesWithTheLast(): void
    {
        $requests = $this->requests([$this->billing, $this->forum], []);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $handler = new EraseModuleHandler($requests);

        $handler(new EraseModule($request->id, 'billing'));
        $handler(new EraseModule($request->id, 'billing'));

        self::assertSame(1, $this->billing->calls, 'A module delivered twice runs once.');
        self::assertSame(RequestState::Running, $requests->find($request->id)?->state);
        $step = $this->bench->store->step($request->id, 'billing');
        self::assertSame(StepState::Completed, $step?->state);
        self::assertSame(['records' => ErasureEffect::Deleted], $step->effects);
        self::assertSame('2026-11-20 10:00:00', $step->completedAt?->format('Y-m-d H:i:s'));

        $this->bench->clock->sleep(30);
        $handler(new EraseModule($request->id, 'forum'));

        $closed = $requests->find($request->id);
        self::assertSame(RequestState::Completed, $closed?->state);
        self::assertSame('2026-11-20 10:00:30', $closed->closedAt?->format('Y-m-d H:i:s'));

        $handler(new EraseModule($request->id, 'forum'));
        self::assertSame(1, $this->forum->calls, 'A closed request runs nothing.');
        self::assertContains(['Erasure request {id} completed.', ['id' => $request->id]], $this->logger->lines);
    }

    public function testAFailingModuleGivesItsStepBackAndNamesNothingButTheRequestAndTheModule(): void
    {
        $failing = new RecordingEraser('forum', true);
        $requests = $this->requests([$this->billing, $failing], []);
        $request = $requests->open(DataSubject::fromString('member-7'));

        try {
            $requests->run($request->id, 'forum');
            self::fail('The failure was swallowed.');
        } catch (ModuleErasureFailed $moduleErasureFailed) {
            self::assertSame(\sprintf('The module "forum" of the erasure request %s failed; the step is given back.', $request->id), $moduleErasureFailed->getMessage());
            self::assertNull($moduleErasureFailed->getPrevious());
            self::assertSame(0, $moduleErasureFailed->getCode());
        }

        $step = $this->bench->store->step($request->id, 'forum');
        self::assertSame(StepState::Pending, $step?->state);
        self::assertSame(1, $step->attempts);
        self::assertSame(RequestState::Running, $requests->find($request->id)?->state);
        self::assertContains(['Erasure request {id}: the module {module} failed with {class}; the step is given back.', ['id' => $request->id, 'module' => 'forum', 'class' => 'RuntimeException']], $this->logger->lines);

        $this->expectException(ModuleErasureFailed::class);
        $requests->run($request->id, 'forum');
    }

    public function testAReportOfAnotherModuleIsAFailure(): void
    {
        $requests = $this->requests([new RecordingEraser('forum', false, 'billing')], []);
        $request = $requests->open(DataSubject::fromString('member-7'));

        try {
            $requests->run($request->id, 'forum');
            self::fail('A report of another module completed the step.');
        } catch (ModuleErasureFailed) {
        }

        self::assertSame(StepState::Pending, $this->bench->store->step($request->id, 'forum')?->state);
    }

    public function testOnceMessengerGivesUpTheStepAndTheRequestNeedAttentionUntilAReplay(): void
    {
        $failing = new RecordingEraser('forum', true);
        $requests = $this->requests([$this->billing, $failing], []);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->run($request->id, 'billing');

        try {
            $requests->run($request->id, 'forum');
        } catch (ModuleErasureFailed) {
        }

        $requests->giveUp($request->id, 'forum');
        $requests->giveUp($request->id, 'billing');

        self::assertSame(RequestState::NeedsAttention, $requests->find($request->id)?->state);
        $step = $this->bench->store->step($request->id, 'forum');
        self::assertSame(StepState::NeedsAttention, $step?->state);
        self::assertSame(AttentionReason::RetriesExhausted, $step->reason);
        self::assertSame(StepState::Completed, $this->bench->store->step($request->id, 'billing')?->state, 'A completed step never needs attention.');
        self::assertContains(['Erasure request {id}: the module {module} needs attention ({reason}).', ['id' => $request->id, 'module' => 'forum', 'reason' => 'retries_exhausted']], $this->logger->lines);

        $this->bench->clock->sleep(60);
        self::assertSame(['forum'], $requests->replay($request->id));

        self::assertSame(RequestState::Running, $requests->find($request->id)?->state);
        self::assertSame(StepState::Pending, $this->bench->store->step($request->id, 'forum')?->state);
        self::assertNull($this->bench->store->step($request->id, 'forum')->reason);
        $replayed = $this->bench->pending()[2] ?? null;
        self::assertSame('{"request":"'.$request->id.'","module":"forum"}', $replayed?->payloadJson);
        self::assertMatchesRegularExpression('/^'.$request->id.':forum:[0-9a-f]{8}$/D', (string) $replayed->idempotencyKey);
        self::assertSame(1, $this->billing->calls);
        self::assertSame(['forum'], $requests->replay($request->id), 'A step still pending is sent again by another replay.');
    }

    public function testAMessageReplayedFromTheFailureTransportBringsTheRequestBackToRunning(): void
    {
        $forum = new RecordingEraser('forum', true);
        $requests = $this->requests([$this->billing, $forum], []);
        $request = $requests->open(DataSubject::fromString('member-7'));

        try {
            $requests->run($request->id, 'forum');
        } catch (ModuleErasureFailed) {
        }

        $requests->giveUp($request->id, 'forum');
        $fixed = $this->requests([$this->billing, new RecordingEraser('forum')], []);

        $fixed->run($request->id, 'forum');

        self::assertSame(StepState::Completed, $this->bench->store->step($request->id, 'forum')?->state);
        self::assertSame(RequestState::Running, $fixed->find($request->id)?->state);
    }

    public function testAStepLeasedToAnotherWorkerIsLeftAloneUntilItsLeaseRunsOut(): void
    {
        $requests = $this->requests([$this->billing], [], lease: 60);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $now = $this->bench->clock->now();
        self::assertTrue($this->bench->store->lease($request->id, 'billing', 'other-worker', $now, $now->modify('+60 seconds')));

        $requests->run($request->id, 'billing');
        self::assertSame(0, $this->billing->calls);

        $this->bench->clock->sleep(60);
        $requests->run($request->id, 'billing');
        self::assertSame(1, $this->billing->calls);
        self::assertFalse($this->bench->store->complete($request->id, 'billing', 'other-worker', [], $this->bench->clock->now()));
    }

    public function testAWorkerLeasesForTheConfiguredSeconds(): void
    {
        $slow = new class implements PersonalDataEraser {
            public ?string $leaseUntil = null;

            public ?Connection $connection = null;

            #[Override]
            public function module(): ModuleName
            {
                return ModuleName::fromString('billing');
            }

            #[Override]
            public function erase(DataSubject $subject): ErasureReport
            {
                $value = $this->connection?->fetchOne("SELECT lease_until FROM phil_privacy_erasure_step WHERE module = 'billing' AND state = 'running'");
                $this->leaseUntil = \is_string($value) ? $value : null;

                return ErasureReport::of('billing', ['records' => ErasureEffect::Deleted]);
            }
        };
        $slow->connection = $this->bench->connection;

        $this->requestsWith([$slow], 120)->run($this->requestsWith([$slow], 120)->open(DataSubject::fromString('member-7'))->id, 'billing');
        self::assertSame('2026-11-20 10:02:00', $slow->leaseUntil);

        $this->requestsWith([$slow])->run($this->requestsWith([$slow])->open(DataSubject::fromString('member-8'))->id, 'billing');
        self::assertSame('2026-11-20 10:15:00', $slow->leaseUntil);
    }

    public function testAModuleThatIsNoLongerDeclaredNeedsAttention(): void
    {
        $request = $this->requests([$this->billing, $this->forum], [])->open(DataSubject::fromString('member-7'));
        $withoutForum = $this->requests([$this->billing], []);

        $withoutForum->run($request->id, 'forum');

        $step = $this->bench->store->step($request->id, 'forum');
        self::assertSame(StepState::NeedsAttention, $step?->state);
        self::assertSame(AttentionReason::UnknownModule, $step->reason);
        self::assertSame(RequestState::NeedsAttention, $withoutForum->find($request->id)?->state);
        self::assertSame(0, $this->forum->calls);
    }

    public function testAModuleTheRequestDoesNotNameIsRefused(): void
    {
        $requests = $this->requests([$this->billing, $this->forum], []);
        $request = $requests->open(DataSubject::fromString('member-7'), ['billing']);

        $requests->run($request->id, 'forum');

        self::assertSame(0, $this->forum->calls);
        self::assertNull($this->bench->store->step($request->id, 'forum'));
        self::assertContains(['Erasure request {id}: the module {module} is not one of its modules; the message is refused.', ['id' => $request->id, 'module' => 'forum']], $this->logger->lines);
    }

    public function testAnUnknownRequestRunsNothing(): void
    {
        $requests = $this->requests([$this->billing], []);

        $requests->run(str_repeat('0', 32), 'billing');
        $requests->run('not-an-id', 'billing');

        self::assertSame(0, $this->billing->calls);
        self::assertNull($requests->progress(str_repeat('0', 32)));
        self::assertNull($requests->find('not-an-id'));
    }

    public function testAnExternalModuleIsSentWithItsKeyAndCompletesOnItsAcknowledgement(): void
    {
        $requests = $this->requests([$this->billing], [$this->mailing]);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $key = $request->id.':mailing';

        $requests->run($request->id, 'mailing');
        $requests->run($request->id, 'mailing');

        self::assertSame([['member-7', $key]], $this->mailing->requests, 'A module waiting for its acknowledgement is not sent twice.');
        self::assertSame(StepState::Running, $this->bench->store->step($request->id, 'mailing')?->state);

        $this->bench->clock->sleep(3600);
        $requests->run($request->id, 'mailing');
        self::assertCount(1, $this->mailing->requests, 'A step waiting for its acknowledgement has no lease to run out.');

        $requests->run($request->id, 'billing');
        self::assertFalse($requests->acknowledge($key, ErasureReport::of('billing', ['contacts' => ErasureEffect::Deleted])), 'A report of another module.');
        self::assertFalse($requests->acknowledge('nothing', ErasureReport::of('mailing', ['contacts' => ErasureEffect::Deleted])));
        self::assertFalse($requests->acknowledge(str_repeat('0', 32).':mailing', ErasureReport::of('mailing', ['contacts' => ErasureEffect::Deleted])));
        self::assertSame(RequestState::Running, $requests->find($request->id)?->state);

        self::assertTrue($requests->acknowledge($key, ErasureReport::of('mailing', ['contacts' => ErasureEffect::Deleted])));
        self::assertFalse($requests->acknowledge($key, ErasureReport::of('mailing', ['contacts' => ErasureEffect::Deleted])), 'An acknowledgement counts once.');

        self::assertSame(['contacts' => ErasureEffect::Deleted], $this->bench->store->step($request->id, 'mailing')?->effects);
        self::assertSame(RequestState::Completed, $requests->find($request->id)?->state);
        self::assertContains(['Erasure request {id}: the module {module} was acknowledged.', ['id' => $request->id, 'module' => 'mailing']], $this->logger->lines);
    }

    public function testAnExternalModuleThatNeverAcknowledgesIsSentAgainByAReplayWithTheSameKey(): void
    {
        $requests = $this->requests([], [$this->mailing]);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->run($request->id, 'mailing');

        self::assertSame(['mailing'], $requests->replay($request->id));
        $requests->run($request->id, 'mailing');

        self::assertSame([['member-7', $request->id.':mailing'], ['member-7', $request->id.':mailing']], $this->mailing->requests);
    }

    public function testAnExternalModuleThatFailsToSendIsGivenBack(): void
    {
        $this->mailing->fails = true;
        $requests = $this->requests([], [$this->mailing]);
        $request = $requests->open(DataSubject::fromString('member-7'));

        try {
            $requests->run($request->id, 'mailing');
            self::fail('The failure was swallowed.');
        } catch (ModuleErasureFailed $moduleErasureFailed) {
            self::assertStringNotContainsString('@', $moduleErasureFailed->getMessage());
        }

        self::assertSame(StepState::Pending, $this->bench->store->step($request->id, 'mailing')?->state);
    }

    public function testOnlyAnOperatorClosesARequestAsFailed(): void
    {
        $requests = $this->requests([$this->billing], []);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $this->bench->clock->sleep(10);

        self::assertTrue($requests->fail($request->id));
        self::assertFalse($requests->fail($request->id));
        self::assertFalse($requests->fail(str_repeat('0', 32)));

        $failed = $requests->find($request->id);
        self::assertSame(RequestState::Failed, $failed?->state);
        self::assertSame('2026-11-20 10:00:10', $failed->closedAt?->format('Y-m-d H:i:s'));
        self::assertSame([], $requests->replay($request->id));
        self::assertSame([], $requests->replay(str_repeat('0', 32)));

        $requests->run($request->id, 'billing');
        self::assertSame(0, $this->billing->calls);
        self::assertContains(['Erasure request {id} closed as failed by an operator.', ['id' => $request->id]], $this->logger->lines);
    }

    public function testAGuestRequestCarriesNoAddressAndIsResolvedInTheWorker(): void
    {
        [$guest] = GuestRequest::issue(GuestRequestKind::Erasure, 'ada@example.org', $this->bench->clock->now(), $this->bench->clock->now()->modify('+1 hour'));
        $this->guests->save($guest);
        $newsletter = new SubjectRecordingEraser();
        $requests = $this->requests([$newsletter, $this->billing], []);

        [$request, $token] = $requests->openForGuest($guest->id);

        self::assertNull($request->subject);
        self::assertSame($guest->id, $request->guestRequestId);
        self::assertTrue($request->isOpenedBy($token));

        foreach ($this->bench->pending() as $record) {
            self::assertStringNotContainsString('@', $record->payloadJson);
            self::assertStringNotContainsString('@', (string) $record->idempotencyKey);
        }

        $rows = $this->bench->connection->fetchAllAssociative('SELECT * FROM phil_privacy_erasure_request');
        self::assertStringNotContainsString('@', (string) json_encode($rows));

        $requests->run($request->id, 'newsletter');
        self::assertSame(['ada@example.org'], $newsletter->erased);
        self::assertArrayHasKey($guest->id, $this->guests->requests, 'The request it came from stays while the erasure runs.');

        $requests->run($request->id, 'billing');
        self::assertSame(RequestState::Completed, $requests->find($request->id)?->state);
        self::assertSame([], $this->guests->requests, 'The request it came from is removed once the erasure is closed.');
    }

    public function testAGuestRequestWhoseOriginIsGoneNeedsAttention(): void
    {
        $requests = $this->requests([$this->billing], []);
        [$request] = $requests->openForGuest(str_repeat('c', 32));

        $requests->run($request->id, 'billing');

        self::assertSame(0, $this->billing->calls);
        self::assertSame(AttentionReason::SubjectUnavailable, $this->bench->store->step($request->id, 'billing')?->reason);
        self::assertSame(RequestState::NeedsAttention, $requests->find($request->id)?->state);
    }

    public function testAGuestRequestWithoutGuestSubjectsNeedsAttention(): void
    {
        [$guest] = GuestRequest::issue(GuestRequestKind::Erasure, 'ada@example.org', $this->bench->clock->now(), $this->bench->clock->now()->modify('+1 hour'));
        $this->guests->save($guest);
        $requests = new ErasureRequests($this->bench->store, $this->bench->outbox, [$this->billing], [], $this->bench->clock, $this->guests);
        [$request] = $requests->openForGuest($guest->id);

        $requests->run($request->id, 'billing');

        self::assertSame(StepState::NeedsAttention, $this->bench->store->step($request->id, 'billing')?->state);
    }

    public function testAFailedGuestRequestRemovesTheRequestItCameFrom(): void
    {
        [$guest] = GuestRequest::issue(GuestRequestKind::Erasure, 'ada@example.org', $this->bench->clock->now(), $this->bench->clock->now()->modify('+1 hour'));
        $this->guests->save($guest);
        $requests = $this->requests([$this->billing], []);
        [$request] = $requests->openForGuest($guest->id);

        $requests->fail($request->id);

        self::assertSame([], $this->guests->requests);
    }

    public function testTheProgressGivesEachModule(): void
    {
        $requests = $this->requests([$this->billing, $this->forum], []);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->run($request->id, 'billing');

        $progress = $requests->progress($request->id);

        self::assertSame($request->id, $progress?->request->id);
        self::assertSame(['billing', 'forum'], array_map(static fn (ErasureStep $step): string => $step->module, $progress->steps));
        self::assertSame(['completed' => 1, 'total' => 2], $progress->counts());
    }

    public function testTwoErasersOfOneModuleAreRefused(): void
    {
        $this->expectException(MisconfiguredPrivacy::class);
        $this->expectExceptionMessageIsOrContains('billing');

        $this->requests([$this->billing], [new RecordingExternalEraser('billing')]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLeases(): iterable
    {
        yield 'none' => [0];
        yield 'over a day' => [86_401];
    }

    #[DataProvider('refusedLeases')]
    public function testALeaseOutOfBoundsIsRefused(int $lease): void
    {
        $this->expectException(MisconfiguredPrivacy::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('An erasure step is leased from 1 to 86400 seconds, not %d.', $lease));

        $this->requests(lease: $lease);
    }

    public function testTheShortestAndLongestLeasesAreAccepted(): void
    {
        self::assertCount(3, $this->requests(lease: 1)->inventory()->modules);
        self::assertCount(3, $this->requests(lease: 86_400)->inventory()->modules);
    }

    public function testTheLogLinesNameTheRequestAndTheModulesOnly(): void
    {
        $requests = $this->requests([$this->billing], [$this->mailing]);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->run($request->id, 'billing');
        $requests->run($request->id, 'mailing');

        self::assertSame([
            ['Erasure request {id} opened for {modules}.', ['id' => $request->id, 'modules' => ['billing', 'mailing'], 'inventory' => $request->inventoryVersion]],
            ['Erasure request {id}: the module {module} completed.', ['id' => $request->id, 'module' => 'billing']],
            ['Erasure request {id}: the module {module} was sent, and waits for its acknowledgement.', ['id' => $request->id, 'module' => 'mailing']],
        ], $this->logger->lines);
        self::assertStringNotContainsString('member-7', (string) json_encode($this->logger->lines));
    }

    public function testAReplayLogsTheModulesItSent(): void
    {
        $requests = $this->requests([$this->billing], []);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->giveUp($request->id, 'billing');

        $requests->replay($request->id);

        self::assertContains(['Erasure request {id} replayed for {modules}.', ['id' => $request->id, 'modules' => ['billing']]], $this->logger->lines);
    }

    /**
     * @param list<PersonalDataEraser>|null         $erasers
     * @param list<ExternalPersonalDataEraser>|null $external
     */
    private function requests(?array $erasers = null, ?array $external = null, int $lease = ErasureRequests::LEASE): ErasureRequests
    {
        return new ErasureRequests(
            $this->bench->store,
            $this->bench->outbox,
            $erasers ?? [$this->forum, $this->billing],
            $external ?? [$this->mailing],
            $this->bench->clock,
            $this->guests,
            new EmailGuestSubjects(),
            $lease,
            $this->logger,
        );
    }

    /**
     * @param list<PersonalDataEraser> $erasers
     */
    private function requestsWith(array $erasers, ?int $lease = null): ErasureRequests
    {
        return null === $lease
            ? new ErasureRequests($this->bench->store, $this->bench->outbox, $erasers, [], $this->bench->clock)
            : new ErasureRequests($this->bench->store, $this->bench->outbox, $erasers, [], $this->bench->clock, null, null, $lease);
    }

    /**
     * @return list<string>
     */
    private function modules(string $id): array
    {
        return array_map(static fn (ErasureStep $step): string => $step->module, $this->bench->store->steps($id));
    }

    public function testALeaseIsThirtyTwoHexadecimalCharacters(): void
    {
        $probe = new class implements PersonalDataEraser {
            public ?string $lease = null;

            public ?Connection $connection = null;

            #[Override]
            public function module(): ModuleName
            {
                return ModuleName::fromString('billing');
            }

            #[Override]
            public function erase(DataSubject $subject): ErasureReport
            {
                $value = $this->connection?->fetchOne("SELECT lease FROM phil_privacy_erasure_step WHERE module = 'billing' AND state = 'running'");
                $this->lease = \is_string($value) ? $value : null;

                return ErasureReport::of('billing', ['records' => ErasureEffect::Deleted]);
            }
        };
        $probe->connection = $this->bench->connection;

        $requests = $this->requestsWith([$probe]);

        $requests->run($requests->open(DataSubject::fromString('member-7'))->id, 'billing');

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', (string) $probe->lease);
    }

    public function testAStepThatNeedsAttentionIsRunningAgainAndSoIsItsRequestEvenWhenTheModuleFailsAgain(): void
    {
        $failing = new RecordingEraser('forum', true);
        $requests = $this->requests([$this->billing, $failing], []);
        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->giveUp($request->id, 'forum');
        self::assertSame(RequestState::NeedsAttention, $requests->find($request->id)?->state);

        try {
            $requests->run($request->id, 'forum');
            self::fail('The module was expected to fail.');
        } catch (ModuleErasureFailed) {
        }

        self::assertSame(RequestState::Running, $requests->find($request->id)?->state);
    }

    public function testAReplayAnswersEveryModuleItSent(): void
    {
        $requests = $this->requests([$this->billing, $this->forum], []);
        $request = $requests->open(DataSubject::fromString('member-7'));

        self::assertSame(['billing', 'forum'], $requests->replay($request->id));
    }

    public function testAnUnknownOrClosedRequestIsLeftAloneWhenAModuleGivesUp(): void
    {
        $requests = $this->requests([$this->billing], []);
        $requests->giveUp(str_repeat('d', 32), 'billing');

        $request = $requests->open(DataSubject::fromString('member-7'));
        $requests->run($request->id, 'billing');

        $requests->giveUp($request->id, 'billing');

        self::assertSame(RequestState::Completed, $requests->find($request->id)?->state);
    }

    public function testWithoutAStoreOfGuestRequestsTheSubjectIsUnavailableAndAClosedRequestRemovesNothing(): void
    {
        $requests = new ErasureRequests($this->bench->store, $this->bench->outbox, [$this->billing], [], $this->bench->clock);
        [$request] = $requests->openForGuest(str_repeat('c', 32));

        $requests->run($request->id, 'billing');
        self::assertSame(StepState::NeedsAttention, $this->bench->store->step($request->id, 'billing')?->state);
        self::assertSame(0, $this->billing->calls);

        self::assertTrue($requests->fail($request->id));
    }

    public function testTheDatesAreThoseOfTheClock(): void
    {
        $request = $this->requests()->open(DataSubject::fromString('member-7'));

        self::assertEquals(new DateTimeImmutable('2026-11-20 10:00:00 UTC'), $request->requestedAt);
        self::assertEquals(new DateTimeImmutable('2026-11-20 10:00:00 UTC'), $this->bench->store->find($request->id)?->requestedAt);
    }
}
