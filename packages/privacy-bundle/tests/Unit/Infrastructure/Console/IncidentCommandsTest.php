<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(IncidentCommand::class)]
#[CoversClass(IncidentsCommand::class)]
final class IncidentCommandsTest extends TestCase
{
    private IncidentRegister $register;

    #[Override]
    protected function setUp(): void
    {
        $this->register = new class implements IncidentRegister {
            /**
             * @var array<string, Incident>
             */
            private array $incidents = [];

            public function save(Incident $incident): void
            {
                $this->incidents[$incident->id] = $incident;
            }

            public function find(string $id): ?Incident
            {
                return $this->incidents[$id] ?? null;
            }

            public function all(): array
            {
                $all = array_values($this->incidents);
                usort($all, static fn (Incident $a, Incident $b): int => $b->discoveredAt <=> $a->discoveredAt);

                return $all;
            }
        };
    }

    public function testAnIncidentIsRecordedThenCompletedAndListed(): void
    {
        $record = $this->command();
        self::assertSame(Command::SUCCESS, $record->execute(['summary' => 'List | exported', '--occurred' => '2026-11-09 08:00', '--category' => ['email', 'phone'], '--affected' => '120', '--serious-risk' => true]));
        self::assertStringContainsString('notify the authority promptly', $record->getDisplay());
        self::assertStringContainsString('notify the people concerned promptly', $record->getDisplay());

        $incident = $this->register->all()[0];
        self::assertEquals(new DateTimeImmutable('2026-11-10T09:00:00Z'), $incident->discoveredAt);
        self::assertEquals(new DateTimeImmutable('2026-11-09T08:00:00Z'), $incident->occurredAt);
        self::assertSame(120, $incident->affected);
        self::assertTrue($incident->seriousRisk);
        self::assertStringContainsString('Incident '.$incident->id.' recorded.', $record->getDisplay());

        $complete = $this->command();
        self::assertSame(Command::SUCCESS, $complete->execute(['--id' => $incident->id, '--authority-notified' => '2026-11-10 15:00', '--measures' => "Folder removed\nlink revoked"]));
        self::assertStringNotContainsString('authority promptly', $complete->getDisplay());
        self::assertStringContainsString('people concerned promptly', $complete->getDisplay());

        $lowered = $this->command();
        self::assertSame(Command::SUCCESS, $lowered->execute(['--id' => $incident->id, '--no-serious-risk' => true, '--closed' => '2026-11-11']));
        self::assertSame("Incident {$incident->id} recorded.\nStill to record for section 7 of the regulation: paragraph 6 (the assessment of the risk: --risk-assessment).\n", $lowered->getDisplay());
        $closed = $this->register->find($incident->id);
        self::assertInstanceOf(Incident::class, $closed);
        self::assertFalse($closed->seriousRisk);
        self::assertSame("Folder removed\nlink revoked", $closed->measures);
        self::assertSame(['email', 'phone'], array_map(static fn (DataCategory $category): string => $category->value, $closed->categories));

        $list = new CommandTester(new IncidentsCommand($this->register));
        self::assertSame(Command::SUCCESS, $list->execute([]));
        self::assertSame(implode("\n", [
            '| Identifier | Discovered | Occurred | Summary | Categories | Affected | Serious risk | Measures | Authority notified | People notified | Closed | Discovered until | Occurred until | Approximate occurrence | Information | Undescribed because | Approximate affected | Risk assessment | Public notice | Public notice reason | Missing (s. 7) |',
            '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|',
            '| '.$incident->id.' | 2026-11-10T09:00:00+00:00 | 2026-11-09T08:00:00+00:00 | List \| exported | email, phone | 120 | no | Folder removed link revoked | 2026-11-10T15:00:00+00:00 | — | 2026-11-11T00:00:00+00:00 | — | — | no | — | — | no | — | — | — | 6 |',
        ])."\n", $list->getDisplay());

        $json = new CommandTester(new IncidentsCommand($this->register));
        self::assertSame(Command::SUCCESS, $json->execute(['--format' => 'json']));
        $rows = json_decode($json->getDisplay(), true);
        self::assertIsArray($rows);
        self::assertIsArray($rows[0]);
        self::assertSame([], $rows[0]['pending_notifications']);
        self::assertNull($rows[0]['subjects_notified_at']);
        self::assertSame(120, $rows[0]['affected']);
    }

    public function testAnIncidentWithoutRiskOwesNothingAndAnEmptyRegisterIsAHeader(): void
    {
        $record = $this->command();
        self::assertSame(Command::SUCCESS, $record->execute(['summary' => 'A mail to the wrong person', '--discovered' => '2026-11-01']));
        self::assertStringNotContainsString('promptly', $record->getDisplay());
        self::assertStringContainsString('Still to record for section 7 of the regulation: paragraph 1 (the information concerned, or why it cannot be described: --category, --information or --undescribed-reason), 3 (when it occurred: --occurred), 5 (how many people: --affected), 6 (the assessment of the risk: --risk-assessment), 8 (the measures: --measures).', $record->getDisplay());
        $incident = $this->register->all()[0];
        self::assertFalse($incident->seriousRisk);
        self::assertSame([], $incident->categories);
        self::assertNull($incident->affected);
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:00:00Z'), $incident->discoveredAt);

        $list = new CommandTester(new IncidentsCommand($this->register));
        $list->execute([]);
        self::assertStringContainsString('| — | A mail to the wrong person | — | — | no | — | — | — | — | — | — | no | — | — | no | — | — | — | 1, 3, 5, 6, 8 |', $list->getDisplay());
    }

    public function testWhatIsRefused(): void
    {
        foreach ([
            [[], Command::INVALID, 'A new incident needs its summary; to complete one, give its --id.'],
            [['summary' => 'x', '--affected' => 'many'], Command::INVALID, '--affected is a number of people.'],
            [['summary' => 'x', '--discovered' => 'not a date'], Command::INVALID, '--discovered is a date'],
            [['summary' => 'x', '--category' => ['Not A Category']], Command::INVALID, 'is not a data category'],
            [['summary' => 'x', '--occurred' => '2026-12-01'], Command::INVALID, 'cannot have occurred after it was discovered'],
            [['--id' => str_repeat('0', 32)], Command::FAILURE, 'No incident has that identifier.'],
            [['summary' => 'x', '--clear' => ['summary']], Command::INVALID, '--clear is one of occurred, occurred-until, discovered-until, affected, authority-notified, subjects-notified, public-notice, closed.'],
            [['summary' => 'x', '--public-notice' => '2026-11-10', '--public-notice-reason' => 'because'], Command::INVALID, '--public-notice-reason is one of increased_injury, excessive_hardship, no_contact_details, prompt_action.'],
            [['summary' => 'x', '--public-notice' => '2026-11-10'], Command::INVALID, 'A public notice is recorded with its date and its reason.'],
            [['summary' => 'x', '--information' => 'Emails', '--undescribed-reason' => 'Lost'], Command::INVALID, 'not both.'],
            [['summary' => 'x', '--occurred' => '2026-11-01', '--occurred-until' => '2026-10-01'], Command::INVALID, 'The period of the incident cannot end before it starts.'],
        ] as [$input, $status, $message]) {
            $tester = $this->command();
            self::assertSame($status, $tester->execute($input), $message);
            self::assertStringContainsString($message, $tester->getDisplay());
        }

        self::assertSame([], $this->register->all());

        $list = new CommandTester(new IncidentsCommand($this->register));
        self::assertSame(Command::INVALID, $list->execute(['--format' => 'csv']));
        self::assertSame("The format is markdown or json.\n", $list->getDisplay());
    }

    public function testTheCategoryOptionIsRepeatedOnTheCommandLine(): void
    {
        $command = new IncidentCommand($this->register, new MockClock('2026-11-10T09:00:00Z'));

        self::assertSame(Command::SUCCESS, $command->run(new StringInput('"List exported" --category=email --category=phone'), new BufferedOutput()));
        self::assertSame(['email', 'phone'], array_map(static fn (DataCategory $category): string => $category->value, $this->register->all()[0]->categories));
    }

    public function testTheNumberOfPeopleIsOnlyDigits(): void
    {
        foreach (['x12', '12x', "12\n"] as $affected) {
            $tester = $this->command();
            self::assertSame(Command::INVALID, $tester->execute(['summary' => 'x', '--affected' => $affected]), $affected);
            self::assertSame("--affected is a number of people.\n", $tester->getDisplay());
        }

        self::assertSame([], $this->register->all());
    }

    public function testAnEmptyOptionCountsAsLeftOut(): void
    {
        $tester = $this->command();
        self::assertSame(Command::SUCCESS, $tester->execute(['summary' => 'x', '--id' => '', '--measures' => '']));

        $incident = $this->register->all()[0];
        self::assertSame('x', $incident->summary);
        self::assertSame('', $incident->measures);
    }

    public function testTheJsonIsIndentedAndKeepsAccentsAndSlashes(): void
    {
        $this->register->save(new Incident(str_repeat('a', 32), 'Café / bar', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], null, false));

        $json = new CommandTester(new IncidentsCommand($this->register));
        self::assertSame(Command::SUCCESS, $json->execute(['--format' => 'json']));
        self::assertStringStartsWith("[\n    {\n        \"id\": \"".str_repeat('a', 32)."\",\n", $json->getDisplay());
        self::assertStringContainsString('"summary": "Café / bar",', $json->getDisplay());
        self::assertStringContainsString('"discovered_at": "2026-11-10T09:00:00+00:00",', $json->getDisplay());
    }

    private function command(): CommandTester
    {
        return new CommandTester(new IncidentCommand($this->register, new MockClock('2026-11-10T09:00:00Z')));
    }

    public function testEveryParagraphIsGivenOnTheCommandLineAndPrinted(): void
    {
        $record = $this->command();
        self::assertSame(Command::SUCCESS, $record->execute([
            'summary' => 'Backup bucket left public',
            '--discovered' => '2026-11-08',
            '--discovered-until' => '2026-11-09',
            '--occurred' => '2026-10-01',
            '--occurred-until' => '2026-11-08',
            '--approximate-occurrence' => true,
            '--information' => 'Names and postal addresses',
            '--affected' => '3000',
            '--approximate-affected' => true,
            '--serious-risk' => true,
            '--risk-assessment' => 'Addresses only, no financial data; the bucket was indexed.',
            '--measures' => 'Bucket closed',
            '--authority-notified' => '2026-11-09 10:00',
            '--public-notice' => '2026-11-10',
            '--public-notice-reason' => 'no_contact_details',
        ]));
        $incident = $this->register->all()[0];
        self::assertSame("Incident {$incident->id} recorded.\n", $record->getDisplay(), 'Nothing is owed and nothing is missing.');
        self::assertEquals(new DateTimeImmutable('2026-11-09T00:00:00Z'), $incident->discoveredUntil);
        self::assertEquals(new DateTimeImmutable('2026-11-08T00:00:00Z'), $incident->occurredUntil);
        self::assertTrue($incident->occurrenceApproximate);
        self::assertTrue($incident->affectedApproximate);
        self::assertSame('Names and postal addresses', $incident->information);
        self::assertSame(PublicNoticeReason::NoContactDetails, $incident->publicNoticeReason);

        $json = new CommandTester(new IncidentsCommand($this->register));
        self::assertSame(Command::SUCCESS, $json->execute(['--format' => 'json']));
        $rows = json_decode($json->getDisplay(), true);
        self::assertIsArray($rows);
        self::assertIsArray($rows[0]);
        self::assertSame([
            'discovered_until' => '2026-11-09T00:00:00+00:00',
            'occurred_until' => '2026-11-08T00:00:00+00:00',
            'occurrence_approximate' => true,
            'information' => 'Names and postal addresses',
            'undescribed_reason' => '',
            'affected_approximate' => true,
            'risk_assessment' => 'Addresses only, no financial data; the bucket was indexed.',
            'public_notice_at' => '2026-11-10T00:00:00+00:00',
            'public_notice_reason' => 'no_contact_details',
            'missing_paragraphs' => [],
            'revisions' => [],
        ], \array_slice($rows[0], 12));

        $markdown = new CommandTester(new IncidentsCommand($this->register));
        $markdown->execute([]);
        self::assertStringEndsWith('| 2026-11-09T00:00:00+00:00 | 2026-11-08T00:00:00+00:00 | yes | Names and postal addresses | — | yes | Addresses only, no financial data; the bucket was indexed. | 2026-11-10T00:00:00+00:00 | no_contact_details | — |'."\n", $markdown->getDisplay());
        self::assertStringNotContainsString('Correction', $markdown->getDisplay());

        $cleared = $this->command();
        self::assertSame(Command::SUCCESS, $cleared->execute(['--id' => $incident->id, '--clear' => ['authority-notified', 'public-notice', 'occurred-until', 'discovered-until'], '--no-approximate-affected' => true, '--no-approximate-occurrence' => true]));
        self::assertStringContainsString('notify the authority promptly', $cleared->getDisplay());
        self::assertStringContainsString('notify the people concerned promptly', $cleared->getDisplay());
        self::assertStringContainsString('Still to record for section 7 of the regulation: paragraph 7 (the notifications still owed).', $cleared->getDisplay());
        $after = $this->register->find($incident->id);
        self::assertInstanceOf(Incident::class, $after);
        self::assertSame([null, null, null, null, false, false], [$after->authorityNotifiedAt, $after->publicNoticeAt, $after->occurredUntil, $after->discoveredUntil, $after->affectedApproximate, $after->occurrenceApproximate]);
        self::assertSame(3000, $after->affected);
    }

    public function testTheSummaryAndTheDatesOfALineAreCorrected(): void
    {
        $this->register->save(new Incident(str_repeat('a', 32), 'Tpyo', new DateTimeImmutable('2026-11-10T09:00:00Z'), new DateTimeImmutable('2026-11-09T09:00:00Z'), [], 5, false));

        $tester = $this->command();
        self::assertSame(Command::SUCCESS, $tester->execute(['summary' => 'Typo', '--id' => str_repeat('a', 32), '--discovered' => '2026-11-09 10:00', '--occurred' => '2026-11-08', '--undescribed-reason' => 'The log was overwritten', '--risk-assessment' => 'No sensitive data.', '--measures' => 'Log kept longer']));
        self::assertSame('Incident '.str_repeat('a', 32)." recorded.\n", $tester->getDisplay());

        $corrected = $this->register->find(str_repeat('a', 32));
        self::assertInstanceOf(Incident::class, $corrected);
        self::assertSame(['Typo', 'The log was overwritten', 5], [$corrected->summary, $corrected->undescribedReason, $corrected->affected]);
        self::assertEquals(new DateTimeImmutable('2026-11-09T10:00:00Z'), $corrected->discoveredAt);
        self::assertEquals(new DateTimeImmutable('2026-11-08T00:00:00Z'), $corrected->occurredAt);

        $cleared = $this->command();
        self::assertSame(Command::SUCCESS, $cleared->execute(['--id' => str_repeat('a', 32), '--clear' => ['occurred', 'affected']]));
        self::assertStringContainsString('paragraph 3 (when it occurred: --occurred), 5 (how many people: --affected).', $cleared->getDisplay());
    }

    public function testTheCorrectionsOfARegisterThatKeepsThemArePrinted(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (PrivacyTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }

        $register = new DbalIncidentRegister($connection, new MockClock('2026-11-12T08:00:00Z'));
        $incident = new Incident(str_repeat('a', 32), 'Tpyo | here', new DateTimeImmutable('2026-11-10T09:00:00Z'), null, [], null, false);
        $register->save($incident);
        $register->save($incident->update(summary: 'Typo'));

        $markdown = new CommandTester(new IncidentsCommand($register));
        self::assertSame(Command::SUCCESS, $markdown->execute([]));
        self::assertStringEndsWith(implode("\n", [
            '',
            '| Identifier | Correction | Field | Previous value | Corrected |',
            '|---|---|---|---|---|',
            '| '.str_repeat('a', 32).' | 1 | summary | Tpyo \\| here | 2026-11-12T08:00:00+00:00 |',
        ])."\n", $markdown->getDisplay());

        $json = new CommandTester(new IncidentsCommand($register));
        $json->execute(['--format' => 'json']);

        $rows = json_decode($json->getDisplay(), true);
        self::assertIsArray($rows);
        self::assertIsArray($rows[0]);
        self::assertSame([['incident_id' => str_repeat('a', 32), 'sequence' => 1, 'field' => 'summary', 'previous' => 'Tpyo | here', 'revised_at' => '2026-11-12T08:00:00+00:00']], $rows[0]['revisions']);
    }
}
