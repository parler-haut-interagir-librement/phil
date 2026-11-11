<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit\Offline;

use DateTimeImmutable;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Exception\InvalidOfflineBatch;
use Phil\Checkin\Offline\OfflineBatch;
use Phil\Checkin\Offline\OfflineScan;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OfflineBatch::class)]
#[CoversClass(OfflineScan::class)]
#[CoversClass(InvalidOfflineBatch::class)]
final class OfflineBatchTest extends TestCase
{
    public function testABatchIsReadScanByScan(): void
    {
        $scans = OfflineBatch::read([
            ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1_794_132_000],
            ['id' => 's2', 'credential' => 'T-1', 'direction' => 'out', 'outcome' => 'checked_out', 'scannedAt' => 0],
            ['id' => 's3', 'credential' => 'T-2', 'direction' => 'in', 'outcome' => 'revoked', 'scannedAt' => 5],
        ]);

        self::assertCount(3, $scans);
        self::assertSame('s1', $scans[0]->id->value);
        self::assertSame('T-1', $scans[0]->credentialId);
        self::assertSame(Direction::In, $scans[0]->direction);
        self::assertSame(ScanOutcome::Admitted, $scans[0]->outcome);
        self::assertSame(1_794_132_000, $scans[0]->scannedAt->getTimestamp());
        self::assertSame(Direction::Out, $scans[1]->direction);
        self::assertSame(0, $scans[1]->scannedAt->getTimestamp());
        self::assertSame(ScanOutcome::Revoked, $scans[2]->outcome);
        self::assertSame([], OfflineBatch::read([]));
    }

    public function testABatchIsAList(): void
    {
        foreach (['scans', ['a' => []], [1 => []]] as $batch) {
            try {
                OfflineBatch::read($batch);
                self::fail('Not a list.');
            } catch (InvalidOfflineBatch $invalidOfflineBatch) {
                self::assertSame('A batch is a list of scans.', $invalidOfflineBatch->getMessage());
            }
        }
    }

    public function testABatchHasALimit(): void
    {
        $scan = ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1];

        self::assertSame(500, OfflineBatch::LIMIT);
        self::assertCount(2, OfflineBatch::read([$scan, $scan], 2));

        $this->expectException(InvalidOfflineBatch::class);
        $this->expectExceptionMessageIsOrContains('A batch holds at most 2 scans.');

        OfflineBatch::read([$scan, $scan, $scan], 2);
    }

    public function testTheDefaultLimitIsFiveHundred(): void
    {
        $scan = ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1];

        self::assertCount(500, OfflineBatch::read(array_fill(0, 500, $scan)));

        $this->expectException(InvalidOfflineBatch::class);

        OfflineBatch::read(array_fill(0, 501, $scan));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function malformed(): iterable
    {
        $shape = 'A scan has an "id", a "credential", a "direction" and an "outcome", all texts, and a "scannedAt" timestamp.';
        $scan = ['id' => 's1', 'credential' => 'T-1', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1];

        yield 'not an object' => ['s1', 'a scan is an object.'];
        yield 'no id' => [['id' => null] + $scan, $shape];
        yield 'no credential' => [['credential' => 7] + $scan, $shape];
        yield 'no direction' => [['direction' => null] + $scan, $shape];
        yield 'no outcome' => [['outcome' => null] + $scan, $shape];
        yield 'no instant' => [['scannedAt' => '1'] + $scan, $shape];
        yield 'a negative instant' => [['scannedAt' => -1] + $scan, $shape];
        yield 'a bad id' => [['id' => 'a b'] + $scan, 'A scan identifier is'];
        yield 'a bad credential' => [['credential' => 'T 1'] + $scan, 'A credential identifier is 1 to 128 printable ASCII characters.'];
        yield 'a bad direction' => [['direction' => 'up'] + $scan, 'A direction is "in" or "out".'];
        yield 'a bad outcome' => [['outcome' => 'maybe'] + $scan, 'The outcome is not one of the library.'];
        yield 'an unknown code' => [['outcome' => 'unknown'] + $scan, 'A scan made offline is of a credential of the manifest, which opens the checkpoint.'];
        yield 'a collision' => [['outcome' => 'collision'] + $scan, 'A scan made offline is of a credential of the manifest, which opens the checkpoint.'];
        yield 'a wrong checkpoint' => [['outcome' => 'wrong_checkpoint'] + $scan, 'A scan made offline is of a credential of the manifest, which opens the checkpoint.'];
        yield 'an admission out' => [['direction' => 'out'] + $scan, 'An admission is an entry and a check-out an exit.'];
        yield 'a check-out in' => [['outcome' => 'checked_out'] + $scan, 'An admission is an entry and a check-out an exit.'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedScanRefusesTheBatchAtItsPosition(mixed $scan, string $message): void
    {
        $valid = ['id' => 's0', 'credential' => 'T-0', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1];

        $this->expectException(InvalidOfflineBatch::class);
        $this->expectExceptionMessageIsOrContains('Scan 1 of the batch: '.$message);

        OfflineBatch::read([$valid, $scan]);
    }

    public function testAScanIsBuiltFromItsParts(): void
    {
        $this->expectException(InvalidCheckin::class);

        OfflineScan::of(ScanId::of('s1'), '', Direction::In, ScanOutcome::Admitted, new DateTimeImmutable());
    }
}
