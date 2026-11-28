<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit;

use DateTimeImmutable;
use Phil\ParticipantSafetyInfo\AccessReason;
use Phil\ParticipantSafetyInfo\AccessRecord;
use Phil\ParticipantSafetyInfo\AccessWindow;
use Phil\ParticipantSafetyInfo\Contract\AccessJournal;
use Phil\ParticipantSafetyInfo\Event\SafetyInfoErased;
use Phil\ParticipantSafetyInfo\Event\SafetyInfoRead;
use Phil\ParticipantSafetyInfo\Event\SafetyInfoSubmitted;
use Phil\ParticipantSafetyInfo\Exception\AccessDenied;
use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use Phil\ParticipantSafetyInfo\Exception\UnreadableSafetyInfo;
use Phil\ParticipantSafetyInfo\KeyManagement\KeyManagementSealer;
use Phil\ParticipantSafetyInfo\Memory\InMemoryAccessJournal;
use Phil\ParticipantSafetyInfo\Memory\InMemorySafetyInfoStore;
use Phil\ParticipantSafetyInfo\Role;
use Phil\ParticipantSafetyInfo\SafetyInfo;
use Phil\ParticipantSafetyInfo\SafetyInfoVault;
use Phil\ParticipantSafetyInfo\SealedSafetyInfo;
use Phil\ParticipantSafetyInfo\Tests\Support\FailingJournal;
use Phil\ParticipantSafetyInfo\Tests\Support\FixedRoles;
use Phil\ParticipantSafetyInfo\Tests\Support\FixedWindows;
use Phil\ParticipantSafetyInfo\Tests\Support\Info;
use Phil\ParticipantSafetyInfo\Tests\Support\RecordingDispatcher;
use Phil\ParticipantSafetyInfo\Tests\Support\RecordingSealer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

#[CoversClass(SafetyInfoVault::class)]
#[CoversClass(AccessDenied::class)]
#[CoversClass(SafetyInfoSubmitted::class)]
#[CoversClass(SafetyInfoRead::class)]
#[CoversClass(SafetyInfoErased::class)]
final class SafetyInfoVaultTest extends TestCase
{
    private InMemorySafetyInfoStore $store;

    private InMemoryAccessJournal $journal;

    private RecordingDispatcher $events;

    private MockClock $clock;

    private FixedWindows $windows;

    private SafetyInfoVault $vault;

    private InMemoryKms $kms;

    protected function setUp(): void
    {
        $this->store = new InMemorySafetyInfoStore();
        $this->journal = new InMemoryAccessJournal();
        $this->events = new RecordingDispatcher();
        $this->clock = new MockClock('2026-12-02 10:00:00');
        $this->windows = new FixedWindows([
            'con' => new AccessWindow(new DateTimeImmutable('2026-11-29 00:00:00'), new DateTimeImmutable('2026-12-06 00:00:00')),
            'later' => new AccessWindow(new DateTimeImmutable('2027-01-10 00:00:00'), new DateTimeImmutable('2027-01-20 00:00:00')),
            'past' => new AccessWindow(new DateTimeImmutable('2026-10-01 00:00:00'), new DateTimeImmutable('2026-10-05 00:00:00')),
        ]);
        $this->kms = new InMemoryKms('kms');
        $this->vault = $this->vault();
    }

    public function testABadParticipantIsRefusedBeforeAnythingIsSealed(): void
    {
        try {
            $this->vault->submit('con', '', Info::sample());
            self::fail('An empty participant passed.');
        } catch (InvalidSafetyInfo $invalidSafetyInfo) {
            self::assertStringStartsWith('A participant is named', $invalidSafetyInfo->getMessage());
        }

        self::assertSame(0, $this->kms->calls);
    }

    public function testAParticipantSubmitsAndReadsTheirOwn(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());

        self::assertEquals(Info::sample(), $this->vault->readOwn('con', 'sam'));
        self::assertNull($this->vault->readOwn('con', 'alex'));
        self::assertNull($this->vault->readOwn('later', 'sam'));
        self::assertEquals([new SafetyInfoSubmitted('con', 'sam')], $this->events->events);
        self::assertSame([], $this->journal->recordsOf('con', 'sam', 10));
    }

    public function testTheStoreKeepsOnlyTheSealedTextUntilTheWindowCloses(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $record = $this->store->get('con', 'sam');

        self::assertInstanceOf(SealedSafetyInfo::class, $record);
        self::assertStringNotContainsString('Peanuts', $record->sealed);
        $bytes = base64_decode($record->sealed, true);
        self::assertIsString($bytes);
        self::assertStringNotContainsString('Peanuts', $bytes);
        self::assertEquals(new DateTimeImmutable('2026-12-02 10:00:00'), $record->sealedAt);
        self::assertEquals(new DateTimeImmutable('2026-12-06 00:00:00'), $record->retainUntil);
    }

    public function testASubmissionBeforeTheWindowOpensIsKept(): void
    {
        $this->vault->submit('later', 'sam', Info::sample());

        self::assertEquals(Info::sample(), $this->vault->readOwn('later', 'sam'));
    }

    public function testASecondSubmissionReplacesTheFirst(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->vault->submit('con', 'sam', new SafetyInfo([], 'Shellfish'));

        self::assertSame('Shellfish', $this->vault->readOwn('con', 'sam')?->allergies);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function closedEvents(): iterable
    {
        yield 'an event without a window' => ['unknown'];
        yield 'an event whose window has closed' => ['past'];
    }

    #[DataProvider('closedEvents')]
    public function testNobodySubmitsForAnEventClosedOrWithoutAWindow(string $event): void
    {
        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessageIsOrContains('This safety information is not available to you.');

        try {
            $this->vault->submit($event, 'sam', Info::sample());
        } finally {
            self::assertNull($this->store->get($event, 'sam'));
            self::assertSame([], $this->events->events);
        }
    }

    public function testASubmissionAtTheClosingIsRefused(): void
    {
        $this->clock->modify('2026-12-06 00:00:00');

        $this->expectException(AccessDenied::class);

        $this->vault->submit('con', 'sam', Info::sample());
    }

    public function testAResponsiblePersonReadsDuringTheWindowAndTheReadingIsJournaled(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->events->events = [];

        self::assertEquals(Info::sample(), $this->vault->read('con', 'sam', 'lead'));
        self::assertEquals(
            [new AccessRecord('con', 'sam', 'lead', AccessReason::Responsible, new DateTimeImmutable('2026-12-02 10:00:00'))],
            $this->journal->recordsOf('con', 'sam', 10),
        );
        self::assertEquals([new SafetyInfoRead('con', 'sam', 'lead', AccessReason::Responsible)], $this->events->events);
    }

    public function testAResponsiblePersonLearnsThatNothingWasSentWithoutAJournalEntry(): void
    {
        self::assertNull($this->vault->read('con', 'sam', 'lead'));
        self::assertSame([], $this->journal->recordsOf('con', 'sam', 10));
        self::assertSame([], $this->events->events);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedReaders(): iterable
    {
        yield 'a member of the team' => ['con', 'volunteer', '2026-12-02 10:00:00'];
        yield 'a stranger' => ['con', 'stranger', '2026-12-02 10:00:00'];
        yield 'the participant through the responsible path' => ['con', 'sam', '2026-12-02 10:00:00'];
        yield 'a responsible person of another event' => ['con', 'other-lead', '2026-12-02 10:00:00'];
        yield 'a responsible person before the window' => ['con', 'lead', '2026-11-28 23:59:59'];
        yield 'a responsible person at the closing' => ['con', 'lead', '2026-12-06 00:00:00'];
        yield 'a responsible person of an event without a window' => ['unwindowed', 'lead', '2026-12-02 10:00:00'];
    }

    #[DataProvider('refusedReaders')]
    public function testAnyoneElseIsRefusedTheSameWayAndNothingIsJournaled(string $event, string $reader, string $at): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->store->save(new SealedSafetyInfo('unwindowed', 'sam', 'x', new DateTimeImmutable(), new DateTimeImmutable()));
        $this->events->events = [];
        $this->clock->modify($at);

        try {
            $this->vault->read($event, 'sam', $reader);
            self::fail('The reading passed.');
        } catch (AccessDenied $accessDenied) {
            self::assertSame('This safety information is not available to you.', $accessDenied->getMessage());
        }

        self::assertSame([], $this->journal->recordsOf($event, 'sam', 10));
        self::assertSame([], $this->events->events);
    }

    public function testAMemberOfTheTeamBreaksTheGlassWithAReason(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->events->events = [];

        self::assertEquals(Info::sample(), $this->vault->breakGlass('con', 'sam', 'volunteer', '  Fainted near hall B.  '));
        self::assertEquals(
            [new AccessRecord('con', 'sam', 'volunteer', AccessReason::BreakGlass, new DateTimeImmutable('2026-12-02 10:00:00'), 'Fainted near hall B.')],
            $this->journal->recordsOf('con', 'sam', 10),
        );
        self::assertEquals([new SafetyInfoRead('con', 'sam', 'volunteer', AccessReason::BreakGlass)], $this->events->events);
    }

    public function testAResponsiblePersonMayBreakTheGlassToo(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());

        self::assertEquals(Info::sample(), $this->vault->breakGlass('con', 'sam', 'lead', 'Fainted near hall B.'));
        self::assertSame(AccessReason::BreakGlass, $this->journal->recordsOf('con', 'sam', 1)[0]->reason);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedGlass(): iterable
    {
        yield 'a stranger' => ['stranger', '2026-12-02 10:00:00'];
        yield 'a member of the team outside the window' => ['volunteer', '2026-12-06 00:00:00'];
    }

    #[DataProvider('refusedGlass')]
    public function testTheGlassBreaksOnlyForTheTeamDuringTheWindow(string $reader, string $at): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->clock->modify($at);

        try {
            $this->vault->breakGlass('con', 'sam', $reader, 'Fainted near hall B.');
            self::fail('The glass broke.');
        } catch (AccessDenied) {
            self::assertSame([], $this->journal->recordsOf('con', 'sam', 10));
        }
    }

    public function testTheGlassNeedsAReasonOfTenCharacters(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());

        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains('The reason for breaking the glass holds 10 to 500 characters.');

        $this->vault->breakGlass('con', 'sam', 'volunteer', '  short  ');
    }

    public function testAJournalThatFailsStopsTheReading(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->events->events = [];
        $vault = $this->vault(new FailingJournal());

        try {
            $vault->read('con', 'sam', 'lead');
            self::fail('The reading passed without its journal.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The journal is down.', $runtimeException->getMessage());
        }

        self::assertSame([], $this->events->events);
    }

    public function testARecordMovedToAnotherParticipantDoesNotOpen(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $record = $this->store->get('con', 'sam');
        self::assertInstanceOf(SealedSafetyInfo::class, $record);
        $this->store->save(new SealedSafetyInfo('con', 'alex', $record->sealed, $record->sealedAt, $record->retainUntil));

        $this->expectException(UnreadableSafetyInfo::class);

        $this->vault->readOwn('con', 'alex');
    }

    public function testARecordMovedToAnotherEventDoesNotOpenForAResponsiblePerson(): void
    {
        $this->vault->submit('later', 'sam', Info::sample());
        $record = $this->store->get('later', 'sam');
        self::assertInstanceOf(SealedSafetyInfo::class, $record);
        $this->store->save(new SealedSafetyInfo('con', 'sam', $record->sealed, $record->sealedAt, $record->retainUntil));

        $this->expectException(UnreadableSafetyInfo::class);

        $this->vault->read('con', 'sam', 'lead');
    }

    public function testAParticipantCopiesTheirInformationToAnotherEvent(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->events->events = [];

        self::assertTrue($this->vault->copy('con', 'later', 'sam'));
        self::assertEquals(Info::sample(), $this->vault->readOwn('later', 'sam'));
        self::assertEquals(new DateTimeImmutable('2027-01-20 00:00:00'), $this->store->get('later', 'sam')?->retainUntil);
        self::assertEquals([new SafetyInfoSubmitted('later', 'sam')], $this->events->events);
        self::assertFalse($this->vault->copy('con', 'later', 'alex'));
        self::assertNull($this->vault->readOwn('later', 'alex'));
    }

    public function testAParticipantErasesTheirOwn(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->events->events = [];

        self::assertTrue($this->vault->erase('con', 'sam'));
        self::assertEquals([new SafetyInfoErased('con', 'sam')], $this->events->events);
        self::assertNull($this->vault->readOwn('con', 'sam'));
        self::assertFalse($this->vault->erase('con', 'sam'));
        self::assertEquals([new SafetyInfoErased('con', 'sam')], $this->events->events);
    }

    public function testAParticipantSeesWhoReadTheirInformation(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->vault->read('con', 'sam', 'lead');

        $this->clock->sleep(60);
        $this->vault->breakGlass('con', 'sam', 'volunteer', 'Fainted near hall B.');

        $readings = $this->vault->readingsOf('con', 'sam');

        self::assertSame(['volunteer', 'lead'], array_map(static fn (AccessRecord $record): string => $record->reader, $readings));
        self::assertCount(1, $this->vault->readingsOf('con', 'sam', 1));
        self::assertCount(2, $this->vault->readingsOf('con', 'sam', 500));
    }

    public function testAPageOfTheJournalReads100RecordsByDefault(): void
    {
        for ($i = 0; $i < 101; ++$i) {
            $this->journal->record(new AccessRecord('con', 'sam', 'lead', AccessReason::Responsible, new DateTimeImmutable('2026-12-02 10:00:00')));
        }

        self::assertCount(100, $this->vault->readingsOf('con', 'sam'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badJournalPages(): iterable
    {
        yield 'zero' => [0];
        yield '501' => [501];
    }

    #[DataProvider('badJournalPages')]
    public function testAPageOfTheJournalReadsOneTo500Records(int $limit): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains('A page of the journal reads 1 to 500 records.');

        $this->vault->readingsOf('con', 'sam', $limit);
    }

    public function testThePurgeDeletesTheRecordsOfClosedWindows(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->vault->submit('later', 'sam', Info::sample());

        self::assertSame(0, $this->vault->purge());

        $this->clock->modify('2026-12-06 00:00:01');

        self::assertSame(1, $this->vault->purge(10_000));
        self::assertNull($this->vault->readOwn('con', 'sam'));
        self::assertNotNull($this->vault->readOwn('later', 'sam'));
    }

    public function testThePurgeDeletes1000RecordsByDefault(): void
    {
        $closed = new DateTimeImmutable('2026-10-05 00:00:00');

        for ($i = 0; $i < 1_001; ++$i) {
            $this->store->save(new SealedSafetyInfo('past', 'p'.$i, 'sealed', $closed, $closed));
        }

        self::assertSame(1_000, $this->vault->purge());
        self::assertSame(1, $this->vault->purge());
    }

    public function testTheSealedTextIsBoundToTheEventAndTheParticipant(): void
    {
        $sealer = new RecordingSealer();
        $vault = new SafetyInfoVault($this->store, $sealer, new FixedRoles([]), $this->windows, $this->journal, $this->clock);
        $vault->submit('con', 'sam', Info::sample());

        self::assertEquals(Info::sample(), $vault->readOwn('con', 'sam'));
        self::assertSame(["phil/participant-safety-info\x1Fv1\x1Fcon\x1Fsam", "phil/participant-safety-info\x1Fv1\x1Fcon\x1Fsam"], $sealer->contexts);
    }

    public function testThePurgeKeepsARecordAtTheExactClosing(): void
    {
        $this->vault->submit('con', 'sam', Info::sample());
        $this->clock->modify('2026-12-06 00:00:00');

        self::assertSame(0, $this->vault->purge(1));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badPurges(): iterable
    {
        yield 'zero' => [0];
        yield '10001' => [10_001];
    }

    #[DataProvider('badPurges')]
    public function testAPurgeDeletesOneTo10000Records(int $limit): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains('A purge deletes 1 to 10000 records at a time.');

        $this->vault->purge($limit);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'submit, event' => ['submit', '', 'An event'];
        yield 'submit, participant' => ['submit', 'participant', 'A participant'];
        yield 'readOwn, event' => ['readOwn', '', 'An event'];
        yield 'readOwn, participant' => ['readOwn', 'participant', 'A participant'];
        yield 'read, event' => ['read', '', 'An event'];
        yield 'read, participant' => ['read', 'participant', 'A participant'];
        yield 'read, reader' => ['read', 'reader', 'A reader'];
        yield 'erase, event' => ['erase', '', 'An event'];
        yield 'erase, participant' => ['erase', 'participant', 'A participant'];
        yield 'readingsOf, event' => ['readingsOf', '', 'An event'];
        yield 'readingsOf, participant' => ['readingsOf', 'participant', 'A participant'];
    }

    #[DataProvider('badIdentifiers')]
    public function testEveryPathChecksItsIdentifiers(string $method, string $empty, string $what): void
    {
        $event = '' === $empty ? '' : 'con';
        $participant = 'participant' === $empty ? '' : 'sam';
        $reader = 'reader' === $empty ? '' : 'lead';

        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains($what.' is named by');

        match ($method) {
            'submit' => $this->vault->submit($event, $participant, Info::sample()),
            'readOwn' => $this->vault->readOwn($event, $participant),
            'read' => $this->vault->read($event, $participant, $reader),
            'erase' => $this->vault->erase($event, $participant),
            default => $this->vault->readingsOf($event, $participant),
        };
    }

    public function testItWorksWithoutADispatcher(): void
    {
        $vault = new SafetyInfoVault($this->store, new KeyManagementSealer($this->kms, 'safety'), new FixedRoles(['con' => ['lead' => Role::Responsible]]), $this->windows, $this->journal, $this->clock);
        $vault->submit('con', 'sam', Info::sample());

        self::assertEquals(Info::sample(), $vault->read('con', 'sam', 'lead'));
        self::assertTrue($vault->erase('con', 'sam'));
    }

    private function vault(?AccessJournal $journal = null): SafetyInfoVault
    {
        return new SafetyInfoVault(
            $this->store,
            new KeyManagementSealer($this->kms, 'safety'),
            new FixedRoles([
                'con' => ['lead' => Role::Responsible, 'volunteer' => Role::Staff, 'stranger' => Role::None],
                'unwindowed' => ['lead' => Role::Responsible],
                'other' => ['other-lead' => Role::Responsible],
            ]),
            $this->windows,
            $journal ?? $this->journal,
            $this->clock,
            $this->events,
        );
    }
}
