<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScanBundle\Application\QuarantinedFile;
use Phil\UploadScanBundle\Application\QuarantinePurger;
use Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand;
use Phil\UploadScanBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\UploadScanBundle\Tests\Unit\Fixture\MemoryInventory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PurgeQuarantineCommand::class)]
final class PurgeQuarantineCommandTest extends TestCase
{
    public function testTheConfiguredRetentionIsApplied(): void
    {
        $inventory = $this->inventory();
        $tester = $this->tester($inventory, 30);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 quarantined file(s) kept for more than 30 day(s) deleted.', $this->flat($tester));
        self::assertSame(['recent', 'month'], array_keys($inventory->files));
    }

    public function testDaysReplacesTheRetentionForOneRun(): void
    {
        $inventory = $this->inventory();
        $tester = $this->tester($inventory, 30);

        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '7']));
        self::assertStringContainsString('2 quarantined file(s) kept for more than 7 day(s) deleted.', $this->flat($tester));
        self::assertSame(['recent'], array_keys($inventory->files));
    }

    public function testADryRunDeletesNothing(): void
    {
        $inventory = $this->inventory();
        $tester = $this->tester($inventory, 7);

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('2 quarantined file(s) kept for more than 7 day(s) would be deleted.', $this->flat($tester));
        self::assertCount(3, $inventory->files);
    }

    public function testOneDayIsAccepted(): void
    {
        $inventory = $this->inventory();
        $tester = $this->tester($inventory, 7);

        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '1', '--max' => '1']));
        self::assertStringContainsString('1 quarantined file(s) kept for more than 1 day(s) deleted.', $this->flat($tester));
    }

    public function testMaxBoundsTheRun(): void
    {
        $inventory = $this->inventory();
        $tester = $this->tester($inventory, 7);

        self::assertSame(Command::SUCCESS, $tester->execute(['--max' => '1']));
        self::assertSame(['recent', 'year'], array_keys($inventory->files));
    }

    public function testTheBoundsAreAtLeastOne(): void
    {
        foreach ([['--days' => '0'], ['--max' => '0'], ['--days' => '-3']] as $input) {
            $inventory = $this->inventory();
            $tester = $this->tester($inventory, 7);

            self::assertSame(Command::INVALID, $tester->execute($input));
            self::assertStringContainsString('--days and --max are at least 1.', $this->flat($tester));
            self::assertCount(3, $inventory->files);
        }
    }

    /**
     * @param int<1, max> $retention
     */
    private function tester(MemoryInventory $inventory, int $retention): CommandTester
    {
        $application = new Application();
        $application->addCommand(new PurgeQuarantineCommand(new QuarantinePurger($inventory, new FrozenClock(new DateTimeImmutable('2026-11-12T12:00:00+00:00'))), $retention));

        return new CommandTester($application->find(PurgeQuarantineCommand::NAME));
    }

    private function inventory(): MemoryInventory
    {
        return new MemoryInventory([
            new QuarantinedFile('recent', 'a.exe', FakeScanner::EICAR_SIGNATURE, new DateTimeImmutable('2026-11-10T00:00:00+00:00'), 68),
            new QuarantinedFile('month', 'b.exe', FakeScanner::EICAR_SIGNATURE, new DateTimeImmutable('2026-10-20T00:00:00+00:00'), 68),
            new QuarantinedFile('year', 'c.exe', FakeScanner::EICAR_SIGNATURE, new DateTimeImmutable('2025-11-01T00:00:00+00:00'), 68),
        ]);
    }

    private function flat(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
