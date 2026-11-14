<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\QuarantinedFile;
use Phil\UploadScanBundle\Application\QuarantinePurger;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;
use Phil\UploadScanBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\UploadScanBundle\Tests\Unit\Fixture\MemoryInventory;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuarantinePurger::class)]
final class QuarantinePurgerTest extends TestCase
{
    private const string NOW = '2026-11-12T12:00:00+00:00';

    public function testTheFilesKeptLongerThanTheRetentionAreDeletedAndLogged(): void
    {
        $inventory = new MemoryInventory([self::file('a', '2026-11-12T00:00:00+00:00'), self::file('b', '2026-10-14T00:00:00+00:00'), self::file('c', '2026-10-13T11:59:59+00:00'), self::file('d', '2026-01-01T00:00:00+00:00')]);
        $logger = new RecordingLogger();

        $purged = new QuarantinePurger($inventory, $this->clock(), $logger)->purge(30);

        self::assertSame(2, $purged);
        self::assertSame(['a', 'b'], array_keys($inventory->files));
        self::assertSame([
            ['notice', 'A quarantined file was purged after its retention.', ['id' => 'c', 'days' => 30]],
            ['notice', 'A quarantined file was purged after its retention.', ['id' => 'd', 'days' => 30]],
        ], $logger->records);
    }

    public function testAFileKeptExactlyTheRetentionIsKept(): void
    {
        $inventory = new MemoryInventory([self::file('a', '2026-10-13T12:00:00+00:00')]);

        self::assertSame(0, new QuarantinePurger($inventory, $this->clock())->purge(30));
        self::assertSame(['a'], array_keys($inventory->files));
    }

    public function testADryRunCountsAndDeletesNothing(): void
    {
        $inventory = new MemoryInventory([self::file('a', '2026-11-12T00:00:00+00:00'), self::file('b', '2026-01-01T00:00:00+00:00'), self::file('c', '2026-01-01T00:00:00+00:00')]);
        $logger = new RecordingLogger();

        self::assertSame(2, new QuarantinePurger($inventory, $this->clock(), $logger)->purge(30, dryRun: true));
        self::assertSame(['a', 'b', 'c'], array_keys($inventory->files));
        self::assertSame([], $logger->records);
    }

    public function testARunDeletesAtMostItsBound(): void
    {
        $inventory = new MemoryInventory(array_map(static fn (int $i): QuarantinedFile => self::file('f'.$i, '2026-01-01T00:00:00+00:00'), range(1, 5)));

        self::assertSame(2, new QuarantinePurger($inventory, $this->clock())->purge(1, 2));
        self::assertSame(['f3', 'f4', 'f5'], array_keys($inventory->files));
        self::assertSame(2, new QuarantinePurger($inventory, $this->clock())->purge(1, 2, true));
    }

    public function testTheInventoryIsReadAPageAtATime(): void
    {
        $old = array_map(static fn (int $i): QuarantinedFile => self::file('o'.$i, '2026-01-01T00:00:00+00:00'), range(1, 150));
        $recent = array_map(static fn (int $i): QuarantinedFile => self::file('r'.$i, '2026-11-12T00:00:00+00:00'), range(1, 120));
        $inventory = new MemoryInventory([...$recent, ...$old]);

        self::assertSame(150, new QuarantinePurger($inventory, $this->clock())->purge(1));
        self::assertCount(120, $inventory->files);
        // 120 kept read over two pages, then the old ones a page at a time, then an empty page.
        self::assertSame(4, $inventory->listings);
    }

    public function testAFileThatCannotBeDeletedIsSkipped(): void
    {
        $inventory = new MemoryInventory([self::file('a', '2026-01-01T00:00:00+00:00'), self::file('b', '2026-01-01T00:00:00+00:00')], undeletable: ['a']);
        $logger = new RecordingLogger();

        self::assertSame(1, new QuarantinePurger($inventory, $this->clock(), $logger)->purge(1, 1));
        self::assertSame(['a'], array_keys($inventory->files));
        self::assertCount(1, $logger->records);
    }

    public function testAFileThatCannotBeDeletedIsNotCountedAndTheRunGoesOn(): void
    {
        $inventory = new MemoryInventory([self::file('a', '2026-01-01T00:00:00+00:00'), self::file('b', '2026-01-01T00:00:00+00:00'), self::file('c', '2026-01-01T00:00:00+00:00')], undeletable: ['a']);

        self::assertSame(2, new QuarantinePurger($inventory, $this->clock())->purge(1, 5));
        self::assertSame(['a'], array_keys($inventory->files));
        // The page is read to its end, then an empty one.
        self::assertSame(2, $inventory->listings);
    }

    public function testWithoutAClockTheSystemTimeIsRead(): void
    {
        $inventory = new MemoryInventory([self::file('a', new DateTimeImmutable('-2 days')->format(\DATE_ATOM)), self::file('b', new DateTimeImmutable('-12 hours')->format(\DATE_ATOM))]);

        self::assertSame(1, new QuarantinePurger($inventory)->purge(1));
        self::assertSame(['b'], array_keys($inventory->files));
    }

    public function testTheFilesystemQuarantineIsPurged(): void
    {
        $directory = Temporary::directory();
        $quarantine = new FilesystemQuarantine($directory, new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
        $recent = new FilesystemQuarantine($directory, $this->clock());
        $old = $quarantine->isolate($this->stream(), 'old.exe', ScanResult::infected(FakeScanner::EICAR_SIGNATURE));
        $kept = $recent->isolate($this->stream(), 'new.exe', ScanResult::infected(FakeScanner::EICAR_SIGNATURE));

        self::assertSame(1, new QuarantinePurger($recent, $this->clock())->purge(30));
        self::assertSame([$kept], array_map(static fn (QuarantinedFile $file): string => $file->id, $recent->files(10)));
        self::assertFileDoesNotExist($directory.'/'.$old.'.quarantine');
        Temporary::remove($directory);
    }

    public function testTheBoundsAreAtLeastOne(): void
    {
        $purger = new QuarantinePurger(new MemoryInventory(), $this->clock());

        foreach ([[0, 1], [1, 0]] as [$days, $max]) {
            try {
                $purger->purge($days, $max);
                self::fail('A purge was accepted.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame('A purge keeps files at least one day and deletes at least one file.', $invalidArgumentException->getMessage());
            }
        }
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable(self::NOW));
    }

    private static function file(string $id, string $at): QuarantinedFile
    {
        return new QuarantinedFile($id, $id.'.exe', FakeScanner::EICAR_SIGNATURE, new DateTimeImmutable($at), 68);
    }

    /**
     * @return resource
     */
    private function stream(): mixed
    {
        $stream = fopen('php://memory', 'r+');
        \assert(false !== $stream);
        fwrite($stream, Temporary::eicar());
        rewind($stream);

        return $stream;
    }
}
