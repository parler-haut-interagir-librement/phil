<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Incident;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\PrivacyBundle\Application\Exception\JournalRefused;
use Phil\PrivacyBundle\Application\Incident\IncidentJournalRecorder;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\JournalEntry;
use Phil\PrivacyBundle\Domain\Incident\JournalKind;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\MemoryRegister;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(IncidentJournalRecorder::class)]
#[CoversClass(JournalRefused::class)]
final class IncidentJournalRecorderTest extends TestCase
{
    private const string ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private DbalIncidentRegister $register;

    private IncidentJournalRecorder $recorder;

    #[Override]
    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        $clock = new MockClock('2026-11-20T10:00:00Z');
        $this->register = new DbalIncidentRegister($connection, $clock);
        $this->register->save(new Incident(self::ID, 'Cards', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], 40, true));
        $this->recorder = new IncidentJournalRecorder($this->register, $clock);
    }

    public function testEachKindIsRecordedAndNumbered(): void
    {
        $communication = $this->recorder->record(self::ID, JournalKind::ThirdPartyCommunication, new DateTimeImmutable('2026-11-10T09:00:00Z'), 'Card issuer', 'Card numbers, to block the cards');
        $consultation = $this->recorder->record(self::ID, JournalKind::OfficerConsultation, new DateTimeImmutable('2026-11-11T09:00:00Z'), 'Privacy officer', 'Serious: card numbers');
        $deferral = $this->recorder->record(self::ID, JournalKind::NoticeDeferral, new DateTimeImmutable('2026-11-12T09:00:00Z'), 'Police', 'Would warn the suspect', new DateTimeImmutable('2026-12-12T00:00:00Z'));
        $lifted = $this->recorder->record(self::ID, JournalKind::DeferralLifted, new DateTimeImmutable('2026-11-20T10:00:00Z'), 'Police');

        self::assertSame([1, 2, 3, 4], [$communication->sequence, $consultation->sequence, $deferral->sequence, $lifted->sequence]);
        self::assertEquals(new DateTimeImmutable('2026-11-20T10:00:00Z'), $communication->recordedAt);
        self::assertSame('', $lifted->details);
        self::assertEquals([$communication, $consultation, $deferral, $lifted], $this->recorder->entries(self::ID));
        self::assertEquals($this->register->journal(self::ID), $this->recorder->entries(self::ID));
    }

    public function testAnUnknownIncidentIsRefused(): void
    {
        $this->expectException(JournalRefused::class);
        $this->expectExceptionMessageIsOrContains('The register has no incident "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb".');

        $this->recorder->record(str_repeat('b', 32), JournalKind::OfficerConsultation, new DateTimeImmutable('2026-11-11T09:00:00Z'), 'Officer', 'Asked');
    }

    public function testAnEntryBeforeTheDiscoveryIsRefused(): void
    {
        try {
            $this->recorder->record(self::ID, JournalKind::OfficerConsultation, new DateTimeImmutable('2026-11-10T08:59:59Z'), 'Officer', 'Asked');
            self::fail('An entry before the discovery is refused.');
        } catch (JournalRefused $journalRefused) {
            self::assertSame('An entry of the journal cannot come before the start of the discovery of its incident.', $journalRefused->getMessage());
        }

        self::assertSame([], $this->recorder->entries(self::ID));
    }

    public function testADeferralIsLiftedOnlyWhileInForce(): void
    {
        foreach ([[], [JournalKind::OfficerConsultation], [JournalKind::NoticeDeferral, JournalKind::DeferralLifted]] as $before) {
            $this->setUp();

            foreach ($before as $kind) {
                $this->recorder->record(self::ID, $kind, new DateTimeImmutable('2026-11-11T09:00:00Z'), 'Police', 'Investigation');
            }

            try {
                $this->recorder->record(self::ID, JournalKind::DeferralLifted, new DateTimeImmutable('2026-11-12T09:00:00Z'), 'Police');
                self::fail('Nothing is deferred.');
            } catch (JournalRefused $journalRefused) {
                self::assertSame('The journal of this incident records no deferral of the notice to lift.', $journalRefused->getMessage());
            }
        }

        $this->recorder->record(self::ID, JournalKind::NoticeDeferral, new DateTimeImmutable('2026-11-13T09:00:00Z'), 'Police', 'Second investigation');
        $this->recorder->record(self::ID, JournalKind::OfficerConsultation, new DateTimeImmutable('2026-11-13T10:00:00Z'), 'Officer', 'Still serious');

        self::assertSame(JournalKind::DeferralLifted, $this->recorder->record(self::ID, JournalKind::DeferralLifted, new DateTimeImmutable('2026-11-14T09:00:00Z'), 'Police')->kind);
    }

    public function testWhetherTheNoticeIsPutOffNow(): void
    {
        $at = new DateTimeImmutable('2026-11-11T09:00:00Z');
        $entry = static fn (JournalKind $kind): JournalEntry => new JournalEntry(self::ID, 1, $kind, $at, 'Police', 'Investigation', null, $at);

        self::assertFalse($this->recorder->isDeferred([]));
        self::assertTrue($this->recorder->isDeferred([$entry(JournalKind::NoticeDeferral)]));
        self::assertTrue($this->recorder->isDeferred([$entry(JournalKind::NoticeDeferral), $entry(JournalKind::OfficerConsultation)]));
        self::assertFalse($this->recorder->isDeferred([$entry(JournalKind::NoticeDeferral), $entry(JournalKind::DeferralLifted)]));
        self::assertTrue($this->recorder->isDeferred([$entry(JournalKind::DeferralLifted), $entry(JournalKind::NoticeDeferral)]));
        self::assertFalse($this->recorder->isDeferred([$entry(JournalKind::ThirdPartyCommunication)]));
    }

    public function testARegisterWithoutAJournalIsRefused(): void
    {
        $recorder = new IncidentJournalRecorder(new MemoryRegister(new Incident(self::ID, 'Cards', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], null, false)), new MockClock('2026-11-20T10:00:00Z'));

        $this->expectException(JournalRefused::class);
        $this->expectExceptionMessageIsOrContains('This register of incidents does not keep a journal (IncidentJournal).');

        $recorder->entries(self::ID);
    }

    public function testARegisterWithoutAJournalRecordsNothing(): void
    {
        $recorder = new IncidentJournalRecorder(new MemoryRegister(new Incident(self::ID, 'Cards', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], null, false)), new MockClock('2026-11-20T10:00:00Z'));

        $this->expectException(JournalRefused::class);
        $this->expectExceptionMessageIsOrContains('This register of incidents does not keep a journal (IncidentJournal).');

        $recorder->record(self::ID, JournalKind::OfficerConsultation, new DateTimeImmutable('2026-11-11T09:00:00Z'), 'Officer', 'Asked');
    }
}
