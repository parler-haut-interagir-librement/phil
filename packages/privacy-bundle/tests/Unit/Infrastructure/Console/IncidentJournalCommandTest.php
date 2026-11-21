<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\PrivacyBundle\Application\Incident\IncidentJournalRecorder;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentJournalCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\MemoryRegister;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(IncidentJournalCommand::class)]
final class IncidentJournalCommandTest extends TestCase
{
    private const string ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private MockClock $clock;

    private IncidentJournalRecorder $recorder;

    #[Override]
    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        // Dates on the command line are read in the time zone of the clock, and written in UTC.
        $this->clock = new MockClock('2026-11-20 10:00:00', 'America/Toronto');
        $register = new DbalIncidentRegister($connection, $this->clock);
        $register->save(new Incident(self::ID, 'Cards', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], 40, true));
        $this->recorder = new IncidentJournalRecorder($register, $this->clock);
    }

    public function testAnEntryIsRecordedThenPrinted(): void
    {
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => self::ID, '--kind' => 'third_party_communication', '--at' => '2026-11-11 08:30', '--party' => 'Card issuer', '--details' => 'Card numbers, to block the cards']));
        self::assertSame('Entry 1 of the journal of incident '.self::ID." recorded (section 3.5 of chapter P-39.1).\n", $tester->getDisplay());

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => self::ID, '--kind' => 'notice_deferral', '--party' => 'Police', '--details' => 'Would warn the suspect', '--until' => '2026-12-12']));
        self::assertStringContainsString('Entry 2 of', $tester->getDisplay());

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => self::ID, '--kind' => 'officer_consultation', '--party' => 'Privacy officer', '--details' => 'Serious']));
        self::assertStringContainsString('(section 3.7 of chapter P-39.1)', $tester->getDisplay());
        self::assertStringNotContainsString('owed promptly', $tester->getDisplay());

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => self::ID, '--kind' => 'deferral_lifted', '--party' => 'Police', '--details' => '']));
        self::assertStringEndsWith("The notice put off is now owed promptly: record it with phil:privacy:incident --subjects-notified.\n", $tester->getDisplay());

        $list = $this->tester();
        self::assertSame(Command::SUCCESS, $list->execute(['incident' => self::ID]));
        self::assertSame(implode("\n", [
            '1. 2026-11-11T13:30:00+00:00, third_party_communication — Card issuer: Card numbers, to block the cards',
            '2. 2026-11-20T15:00:00+00:00, notice_deferral — Police: Would warn the suspect (until 2026-12-12T05:00:00+00:00)',
            '3. 2026-11-20T15:00:00+00:00, officer_consultation — Privacy officer: Serious',
            '4. 2026-11-20T15:00:00+00:00, deferral_lifted — Police',
        ])."\n", $list->getDisplay());
    }

    public function testAnEmptyJournalSaysSo(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['incident' => self::ID]));
        self::assertSame("The journal of this incident is empty.\n", $tester->getDisplay());
    }

    public function testWhatIsRefused(): void
    {
        $kind = $this->tester();
        self::assertSame(Command::INVALID, $kind->execute(['incident' => self::ID, '--kind' => 'gossip', '--party' => 'x', '--details' => 'y']));
        self::assertSame("--kind is one of third_party_communication, notice_deferral, deferral_lifted, officer_consultation.\n", $kind->getDisplay());

        $date = $this->tester();
        self::assertSame(Command::INVALID, $date->execute(['incident' => self::ID, '--kind' => 'notice_deferral', '--party' => 'Police', '--details' => 'y', '--until' => 'someday']));
        self::assertSame("--until is a date, such as 2026-11-10 or \"2026-11-10 14:30\".\n", $date->getDisplay());

        $party = $this->tester();
        self::assertSame(Command::INVALID, $party->execute(['incident' => self::ID, '--kind' => 'officer_consultation', '--details' => 'y']));
        self::assertStringStartsWith('An entry of the journal names who', $party->getDisplay());

        $unknown = $this->tester();
        self::assertSame(Command::FAILURE, $unknown->execute(['incident' => str_repeat('b', 32), '--kind' => 'officer_consultation', '--party' => 'x', '--details' => 'y']));
        self::assertSame('The register has no incident "'.str_repeat('b', 32)."\".\n", $unknown->getDisplay());

        $without = new CommandTester(new IncidentJournalCommand(new IncidentJournalRecorder(new MemoryRegister(new Incident(self::ID, 'x', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], null, false)), $this->clock), $this->clock));
        self::assertSame(Command::FAILURE, $without->execute(['incident' => self::ID]));
        self::assertSame("This register of incidents does not keep a journal (IncidentJournal).\n", $without->getDisplay());
    }

    public function testTheKindOptionListsItsValues(): void
    {
        self::assertSame('What to record: third_party_communication, notice_deferral, deferral_lifted, officer_consultation', $this->command()->getDefinition()->getOption('kind')->getDescription());
    }

    private function tester(): CommandTester
    {
        return new CommandTester($this->command());
    }

    private function command(): IncidentJournalCommand
    {
        return new IncidentJournalCommand($this->recorder, $this->clock);
    }
}
