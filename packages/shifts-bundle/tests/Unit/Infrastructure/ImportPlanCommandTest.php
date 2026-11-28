<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Infrastructure;

use Phil\Shifts\Format\ShiftPlan;
use Phil\ShiftsBundle\Infrastructure\Console\ImportPlanCommand;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ImportPlanCommand::class)]
final class ImportPlanCommandTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ('' !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testAPlanIsImportedThenCorrected(): void
    {
        $stage = new Stage();
        $tester = new CommandTester(new ImportPlanCommand($stage->rosters));

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->plan(2)]));
        self::assertMatchesRegularExpression('/(?<!-)\\b2 planned, 0 corrected, 0 kept as they were\\./', $tester->getDisplay());
        $stage->rosters->signUp('desk-am', 'ada', []);

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->plan(4)]));
        self::assertStringContainsString('0 planned, 0 corrected, 2 kept as they were.', $tester->getDisplay());
        self::assertSame(2, $stage->rosters->find('desk-am')?->shift->capacity);

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->plan(4), '--update' => true]));
        self::assertStringContainsString('0 planned, 2 corrected, 0 kept as they were.', $tester->getDisplay());
        self::assertSame(4, $stage->rosters->find('desk-am')?->shift->capacity);
        self::assertNotNull($stage->rosters->find('desk-am')?->statusOf('ada'));

        $stage->rosters->signUp('desk-am', 'bob', []);
        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->plan(1), '--update' => true]));
        self::assertStringContainsString('desk-am: A roster holds no more places', $tester->getDisplay());
        self::assertStringContainsString('0 planned, 1 corrected, 1 kept as they were.', $tester->getDisplay());
    }

    public function testAFileThatCannotBeRead(): void
    {
        $tester = new CommandTester(new ImportPlanCommand(new Stage()->rosters));

        self::assertSame(Command::FAILURE, $tester->execute(['file' => '/no/such/file.json']));
        self::assertStringContainsString('The file cannot be read.', $tester->getDisplay());

        $this->file = (string) tempnam(sys_get_temp_dir(), 'plan');
        file_put_contents($this->file, '{"format":"other"}');
        self::assertSame(Command::FAILURE, $tester->execute(['file' => $this->file]));
        self::assertStringContainsString('$.format: must be phil-shifts/1', $tester->getDisplay());
    }

    public function testAPlanOfOneMebibyteIsReadAndOneByteMoreIsNot(): void
    {
        $tester = new CommandTester(new ImportPlanCommand(new Stage()->rosters));
        $this->file = (string) tempnam(sys_get_temp_dir(), 'plan');
        $plan = '{"format":"phil-shifts/1","shifts":[]}';

        file_put_contents($this->file, str_pad($plan, ShiftPlan::MAX_BYTES));
        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->file]));

        file_put_contents($this->file, str_pad($plan, ShiftPlan::MAX_BYTES + 1));
        self::assertSame(Command::FAILURE, $tester->execute(['file' => $this->file]));
        self::assertStringContainsString('A plan of shifts is 1 MiB at most.', $tester->getDisplay());
    }

    private function plan(int $capacity): string
    {
        $this->file = '' === $this->file ? (string) tempnam(sys_get_temp_dir(), 'plan') : $this->file;
        file_put_contents($this->file, json_encode(['format' => 'phil-shifts/1', 'shifts' => [
            ['id' => 'desk-am', 'title' => 'Info desk', 'kind' => 'fixed', 'starts_at' => '2026-11-28T09:00:00Z', 'ends_at' => '2026-11-28T12:00:00Z', 'capacity' => $capacity],
            ['id' => 'desk-pm', 'title' => 'Info desk', 'kind' => 'fixed', 'starts_at' => '2026-11-28T13:00:00Z', 'ends_at' => '2026-11-28T16:00:00Z', 'capacity' => $capacity],
        ]], \JSON_THROW_ON_ERROR));

        return $this->file;
    }
}
