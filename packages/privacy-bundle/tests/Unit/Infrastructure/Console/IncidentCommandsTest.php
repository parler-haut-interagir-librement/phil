<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
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
        self::assertSame("Incident {$incident->id} recorded.\n", $lowered->getDisplay());
        $closed = $this->register->find($incident->id);
        self::assertInstanceOf(Incident::class, $closed);
        self::assertFalse($closed->seriousRisk);
        self::assertSame("Folder removed\nlink revoked", $closed->measures);
        self::assertSame(['email', 'phone'], array_map(static fn (DataCategory $category): string => $category->value, $closed->categories));

        $list = new CommandTester(new IncidentsCommand($this->register));
        self::assertSame(Command::SUCCESS, $list->execute([]));
        self::assertSame(implode("\n", [
            '| Identifier | Discovered | Occurred | Summary | Categories | Affected | Serious risk | Measures | Authority notified | People notified | Closed |',
            '|---|---|---|---|---|---|---|---|---|---|---|',
            '| '.$incident->id.' | 2026-11-10T09:00:00+00:00 | 2026-11-09T08:00:00+00:00 | List \| exported | email, phone | 120 | no | Folder removed link revoked | 2026-11-10T15:00:00+00:00 | — | 2026-11-11T00:00:00+00:00 |',
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
        $incident = $this->register->all()[0];
        self::assertFalse($incident->seriousRisk);
        self::assertSame([], $incident->categories);
        self::assertNull($incident->affected);
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:00:00Z'), $incident->discoveredAt);

        $list = new CommandTester(new IncidentsCommand($this->register));
        $list->execute([]);
        self::assertStringContainsString('| — | A mail to the wrong person | — | — | no | — | — | — | — |', $list->getDisplay());
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
}
