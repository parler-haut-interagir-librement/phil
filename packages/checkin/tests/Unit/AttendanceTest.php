<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use DateTimeImmutable;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Attendance::class)]
final class AttendanceTest extends TestCase
{
    public function testACredentialNeverScannedHasNoEntry(): void
    {
        $none = Attendance::none();

        self::assertSame(0, $none->version);
        self::assertSame(0, $none->entries);
        self::assertFalse($none->inside);
        self::assertNull($none->lastEntryAt);
    }

    public function testAKeptAttendanceIsRestored(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00');
        $attendance = Attendance::restore(3, 2, true, $at);

        self::assertSame(3, $attendance->version);
        self::assertSame(2, $attendance->entries);
        self::assertTrue($attendance->inside);
        self::assertSame($at, $attendance->lastEntryAt);
        self::assertSame(2, Attendance::restore(2, 2, false, $at)->entries);
        self::assertSame(4, Attendance::restore(4, 0, false, null)->version);
    }

    /**
     * @return iterable<string, array{int, int, bool, ?DateTimeImmutable}>
     */
    public static function impossible(): iterable
    {
        $at = new DateTimeImmutable('2026-11-08 10:00');

        yield 'negative entries' => [0, -1, false, null];
        yield 'more entries than check-ins' => [1, 2, false, $at];
        yield 'entries without a last entry' => [1, 1, false, null];
        yield 'a last entry without entries' => [1, 0, false, $at];
        yield 'inside without an entry' => [1, 0, true, null];
    }

    #[DataProvider('impossible')]
    public function testAnImpossibleAttendanceIsRefused(int $version, int $entries, bool $inside, ?DateTimeImmutable $lastEntryAt): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('An attendance counts its entries among its check-ins, has a last entry once it has one, and is inside only after an entry.');

        Attendance::restore($version, $entries, $inside, $lastEntryAt);
    }

    #[TestWith([ScanOutcome::AlreadyAdmitted])]
    #[TestWith([ScanOutcome::Expired])]
    public function testARefusalChangesNothing(ScanOutcome $outcome): void
    {
        $attendance = Attendance::restore(1, 1, true, new DateTimeImmutable('2026-11-08 09:00'));

        self::assertSame($attendance, $attendance->after($this->checkIn($outcome, Direction::In, $attendance)));
    }

    public function testAnAdmissionCountsAnEntryAndPutsTheHolderInside(): void
    {
        $checkIn = $this->checkIn(ScanOutcome::Admitted, Direction::In, Attendance::none());
        $after = Attendance::none()->after($checkIn);

        self::assertSame(1, $after->version);
        self::assertSame(1, $after->entries);
        self::assertTrue($after->inside);
        self::assertSame($checkIn->scannedAt, $after->lastEntryAt);
    }

    public function testACheckOutLetsTheHolderOutAndKeepsTheirEntries(): void
    {
        $lastEntry = new DateTimeImmutable('2026-11-08 09:00');
        $inside = Attendance::restore(1, 1, true, $lastEntry);
        $after = $inside->after($this->checkIn(ScanOutcome::CheckedOut, Direction::Out, $inside));

        self::assertSame(2, $after->version);
        self::assertSame(1, $after->entries);
        self::assertFalse($after->inside);
        self::assertSame($lastEntry, $after->lastEntryAt);
    }

    private function checkIn(ScanOutcome $outcome, Direction $direction, Attendance $seen): CheckIn
    {
        $at = new DateTimeImmutable('2026-11-08 10:00');

        return CheckIn::decided(ScanId::of('s1'), Credential::of('T-1'), CheckpointId::of('hall'), $direction, $outcome, DeviceId::of('d1'), $at, $at, $seen);
    }
}
