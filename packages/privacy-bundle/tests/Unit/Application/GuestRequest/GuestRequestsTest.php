<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\GuestRequest;

use DateTimeImmutable;
use Override;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\TooManyGuestRequests;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\GuestRequest\EmailGuestSubjects;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ContextLogger;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ExpiringLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\InterruptingModule;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryGuestRequests;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MessageLogger;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLinkSender;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\ScriptedLockStore;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\SubjectRecordingEraser;
use Phil\PrivacyBundle\Tests\Unit\Fixture\Unzip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(GuestRequests::class)]
#[CoversClass(HeldLock::class)]
#[CoversClass(EmailGuestSubjects::class)]
#[CoversClass(TooManyGuestRequests::class)]
final class GuestRequestsTest extends TestCase
{
    private MemoryGuestRequests $store;

    private RecordingLinkSender $sender;

    private SubjectRecordingEraser $eraser;

    private MockClock $clock;

    private InMemoryStorage $limits;

    private LockFactory $locks;

    private MessageLogger $logger;

    protected function setUp(): void
    {
        $this->store = new MemoryGuestRequests();
        $this->sender = new RecordingLinkSender();
        $this->eraser = new SubjectRecordingEraser();
        $this->clock = new MockClock('2026-11-10T12:00:00Z');
        $this->limits = new InMemoryStorage();
        $this->locks = new LockFactory(new InMemoryStore());
        $this->logger = new MessageLogger();
    }

    public function testALinkIsSentToTheAddressAsNormalizedAndOpensTheRequest(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Access, " Eloise@EXAMPLE.org\n", '203.0.113.1');

        self::assertCount(1, $this->sender->sent);
        [$request, $token] = $this->sender->sent[0];
        self::assertSame('Eloise@example.org', $request->email);
        self::assertEquals($this->clock->now()->modify('+1 hour'), $request->expiresAt);
        self::assertSame($request, $this->store->find($request->id));
        self::assertSame($request, $guests->open($request->id, $token));
        self::assertNull($guests->open($request->id, 'wrong'));
        self::assertNull($guests->open('not-an-id', $token));
        self::assertNull($guests->open(str_repeat('0', 32), $token));
        self::assertSame(1, $this->store->purges);
        self::assertSame(['Request without an account {id} sent.'], $this->logger->messages);
    }

    public function testTheAccessIsTheArchiveOfTheVerifiedAddressAndTheLinkWorksOnce(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];

        self::assertNull($guests->erase($request->id, $token), 'An access link does not erase.');
        $zip = $guests->access($request->id, $token);
        self::assertIsString($zip);
        self::assertSame(['subject' => 'a@example.org', 'list' => 'news'], json_decode(Unzip::files($zip)['newsletter.json'], true));
        self::assertNull($this->store->find($request->id));
        self::assertNull($guests->access($request->id, $token));
        self::assertNull($guests->access($request->id, 'wrong'));
    }

    public function testTheErasureRunsEveryEraserOnTheVerifiedAddress(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Erasure, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];

        self::assertNull($guests->access($request->id, $token), 'An erasure link does not export.');
        $reports = $guests->erase($request->id, $token);
        self::assertIsArray($reports);
        self::assertSame('newsletter', $reports[0]->module->value);
        self::assertSame(['a@example.org'], $this->eraser->erased);
        self::assertNull($this->store->find($request->id));
        self::assertNull($guests->erase($request->id, $token));
    }

    public function testAnIncompleteErasureKeepsTheRequestForTheSameLink(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Erasure, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];
        $this->eraser->fails = true;

        try {
            $guests->erase($request->id, $token);
            self::fail('The failure must reach the caller.');
        } catch (ErasureIncomplete) {
            self::assertNotNull($this->store->find($request->id));
            self::assertTrue($this->locks->createLock('phil_privacy.guest.'.$request->id)->acquire(), 'The lock is released.');
        }

        $this->eraser->fails = false;
        self::assertIsArray($guests->erase($request->id, $token));
    }

    public function testAnExpiredLinkIsRemovedWhenOpened(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];

        $this->clock->sleep(3599);
        self::assertNotNull($guests->open($request->id, $token));
        $this->clock->sleep(1);
        self::assertNull($guests->open($request->id, $token));
        self::assertNull($this->store->find($request->id));
    }

    public function testARequestHeldByAnotherProcessIsNotRunTwice(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];
        $held = $this->locks->createLock('phil_privacy.guest.'.$request->id);
        $held->acquire();

        self::assertNull($guests->access($request->id, $token));
        self::assertNotNull($this->store->find($request->id));
    }

    public function testTheLockLastsFiveMinutesAndIsRenewedBeforeEachModuleAndTheRemoval(): void
    {
        $locks = new RecordingLockStore();
        $this->locks = new LockFactory($locks);
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];
        $guests->access($request->id, $token);

        // Taken, renewed before the one module, then before the request is removed.
        self::assertSame([300.0, 300.0, 300.0], $locks->lifetimes);
    }

    public function testARequestWhoseLockCannotBeTakenIsNotRun(): void
    {
        $locks = new ScriptedLockStore();
        $locks->grants = false;

        $this->locks = new LockFactory($locks);
        $exporter = new InterruptingModule('members', static function (): void {
        });
        $guests = $this->guests(exporters: [$exporter]);
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];

        self::assertNull($guests->access($request->id, $token));
        self::assertSame(0, $exporter->calls);
        self::assertNotNull($this->store->find($request->id));
    }

    public function testAnAccessWhoseLockWasTakenOverStopsAndKeepsTheRequest(): void
    {
        $this->locks = new LockFactory(new ExpiringLockStore($this->clock));
        $after = new InterruptingModule('posts', static function (): void {
        });
        $id = null;
        $taken = new InterruptingModule('members', function () use (&$id): void {
            $this->clock->sleep(GuestRequests::LOCK_TTL + 1);
            self::assertTrue($this->locks->createLock('phil_privacy.guest.'.$id, GuestRequests::LOCK_TTL, false)->acquire());
        });
        $guests = $this->guests(exporters: [$taken, $after]);
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];
        $id = $request->id;
        $this->clock->sleep(-GuestRequests::LOCK_TTL - 1);

        self::assertNull($guests->access($request->id, $token));
        self::assertSame(0, $after->calls);
        self::assertNotNull($this->store->find($request->id), 'The request stays for the execution that holds the lock.');
        self::assertContains('Request without an account {id}: its lock was lost; the request is kept.', $this->logger->messages);
        self::assertContains(['id' => $request->id], $this->logger->contexts);
    }

    public function testAnErasureWhoseLockWasTakenOverAfterItsLastModuleKeepsTheRequest(): void
    {
        $this->locks = new LockFactory(new ExpiringLockStore($this->clock));
        $id = null;
        $taken = new InterruptingModule('members', function () use (&$id): void {
            $this->clock->sleep(GuestRequests::LOCK_TTL + 1);
            self::assertTrue($this->locks->createLock('phil_privacy.guest.'.$id, GuestRequests::LOCK_TTL, false)->acquire());
        });
        $guests = $this->guests(erasers: [$taken]);
        $guests->submit(GuestRequestKind::Erasure, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];
        $id = $request->id;

        self::assertNull($guests->erase($request->id, $token));
        self::assertSame(1, $taken->calls);
        self::assertNotNull($this->store->find($request->id), 'The erasure is run again by the execution that holds the lock.');
    }

    public function testAClientOverItsLimitIsRefusedWithTheTimeToWait(): void
    {
        $guests = $this->guests(perClient: 2);
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        $guests->submit(GuestRequestKind::Access, 'b@example.org', 'client');

        try {
            $guests->submit(GuestRequestKind::Access, 'c@example.org', 'client');
            self::fail('A third request must be refused.');
        } catch (TooManyGuestRequests $tooManyGuestRequests) {
            self::assertGreaterThan(0, $tooManyGuestRequests->retryAfter());
            self::assertLessThanOrEqual(3601, $tooManyGuestRequests->retryAfter());
        }

        $guests->submit(GuestRequestKind::Access, 'c@example.org', 'another client');
        self::assertCount(3, $this->sender->sent);
    }

    public function testAnInvalidAddressCountsAgainstTheClient(): void
    {
        $guests = $this->guests(perClient: 1);

        try {
            $guests->submit(GuestRequestKind::Access, 'not an address', 'client');
            self::fail('An address that cannot receive mail must be refused.');
        } catch (InvalidEmailAddress) {
        }

        $this->expectException(TooManyGuestRequests::class);
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
    }

    public function testAnAddressOverItsLimitIsDroppedSilently(): void
    {
        $guests = $this->guests(perAddress: 1);
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'one');
        $guests->submit(GuestRequestKind::Erasure, 'a@example.org', 'two');
        $guests->submit(GuestRequestKind::Erasure, 'A@EXAMPLE.ORG', 'three');

        self::assertCount(2, $this->sender->sent, 'The part before the @ is case-sensitive; the domain is not.');
        self::assertSame('A@example.org', $this->sender->sent[1][0]->email);
        self::assertContains('A request without an account was dropped: its address received too many links.', $this->logger->messages);
    }

    public function testNoAddressOrClientIsWrittenInTheLimitsStorage(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Access, 'secret@example.org', '203.0.113.9');

        $dump = serialize($this->limits);
        self::assertStringNotContainsString('secret@example.org', $dump);
        self::assertStringNotContainsString('203.0.113.9', $dump);
    }

    public function testEveryLogLineNamesItsRequest(): void
    {
        $logger = new ContextLogger();
        $guests = $this->guests(logger: $logger);
        $guests->submit(GuestRequestKind::Access, 'a@example.org', 'client');
        [$access, $accessToken] = $this->sender->sent[0];
        $guests->access($access->id, $accessToken);
        $guests->submit(GuestRequestKind::Erasure, 'b@example.org', 'client');
        [$erasure, $erasureToken] = $this->sender->sent[1];
        $guests->erase($erasure->id, $erasureToken);

        self::assertSame([
            ['Request without an account {id} sent.', ['id' => $access->id, 'kind' => 'access']],
            ['Request without an account {id} answered.', ['id' => $access->id]],
            ['Request without an account {id} sent.', ['id' => $erasure->id, 'kind' => 'erasure']],
            ['Request without an account {id} answered.', ['id' => $erasure->id]],
        ], $logger->lines);
    }

    public function testAnIdentifierOfTheWrongShapeIsNeverLookedUp(): void
    {
        $store = new class implements GuestRequestStore {
            /**
             * @var list<string>
             */
            public array $lookedUp = [];

            #[Override]
            public function save(GuestRequest $request): void
            {
            }

            #[Override]
            public function find(string $id): ?GuestRequest
            {
                $this->lookedUp[] = $id;

                return null;
            }

            #[Override]
            public function delete(string $id): void
            {
            }

            #[Override]
            public function purgeExpired(DateTimeImmutable $now): int
            {
                return 0;
            }
        };

        $guests = $this->guests(store: $store);

        self::assertNull($guests->open('not-an-id', 'token'));
        self::assertNull($guests->open(str_repeat('A', 32), 'token'));
        self::assertSame([], $store->lookedUp);
        self::assertNull($guests->open(str_repeat('a', 32), 'token'));
        self::assertSame([str_repeat('a', 32)], $store->lookedUp);
    }

    public function testALinkThatDoesNotOpenTakesNoLock(): void
    {
        $locks = new RecordingLockStore();
        $this->locks = new LockFactory($locks);
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Erasure, 'a@example.org', 'client');
        [$request] = $this->sender->sent[0];

        self::assertNull($guests->access($request->id, 'wrong'));
        self::assertNull($guests->erase($request->id, 'wrong'));
        self::assertSame([], $locks->lifetimes);
    }

    public function testTheErasureReturnsTheReportOfEveryModule(): void
    {
        $guests = $this->guests(erasers: [$this->eraser, new RecordingEraser('comments')]);
        $guests->submit(GuestRequestKind::Erasure, 'a@example.org', 'client');
        [$request, $token] = $this->sender->sent[0];

        $reports = $guests->erase($request->id, $token);

        self::assertIsArray($reports);
        self::assertSame(['comments', 'newsletter'], array_map(static fn (ErasureReport $report): string => $report->module->value, $reports));
    }

    public function testEachLimitIsKeyedOnTheKeyedHashOfItsPurposeAndValue(): void
    {
        $guests = $this->guests();
        $guests->submit(GuestRequestKind::Access, 'a@example.org', '203.0.113.9');

        $key = hash_hmac('sha256', 'phil_privacy.guest_requests', 'secret', true);
        $dump = serialize($this->limits);
        self::assertStringContainsString('client-'.hash_hmac('sha256', 'client|203.0.113.9', $key), $dump);
        self::assertStringContainsString('address-'.hash_hmac('sha256', 'address|a@example.org', $key), $dump);
    }

    public function testTheTooManyExceptionWaitsAtLeastOneSecond(): void
    {
        self::assertSame(1, TooManyGuestRequests::retryIn(0)->retryAfter());
        self::assertSame(1, TooManyGuestRequests::retryIn(-5)->retryAfter());
        self::assertSame(30, TooManyGuestRequests::retryIn(30)->retryAfter());
        self::assertInstanceOf(GuestRequest::class, $this->issue());
    }

    private function issue(): GuestRequest
    {
        return GuestRequest::issue(GuestRequestKind::Access, 'a@example.org', $this->clock->now(), $this->clock->now()->modify('+1 hour'))[0];
    }

    /**
     * @param list<PersonalDataEraser>|null   $erasers
     * @param list<PersonalDataExporter>|null $exporters
     */
    private function guests(int $perClient = 5, int $perAddress = 3, ?LoggerInterface $logger = null, ?GuestRequestStore $store = null, ?array $erasers = null, ?array $exporters = null): GuestRequests
    {
        return new GuestRequests(
            $store ?? $this->store,
            $this->sender,
            new EmailGuestSubjects(),
            new SubjectExport($exporters ?? [new FixedExporter('newsletter', ['list' => 'news'])]),
            new SubjectErasure($erasers ?? [$this->eraser]),
            new ExportPackage(new PlainExportReadme(), $this->clock),
            $this->locks,
            $this->clock,
            new RateLimiterFactory(['id' => 'client', 'policy' => 'sliding_window', 'limit' => $perClient, 'interval' => '1 hour'], $this->limits),
            new RateLimiterFactory(['id' => 'address', 'policy' => 'sliding_window', 'limit' => $perAddress, 'interval' => '1 day'], $this->limits),
            'secret',
            'PT1H',
            $logger ?? $this->logger,
        );
    }
}
