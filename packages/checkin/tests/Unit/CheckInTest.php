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
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckIn::class)]
final class CheckInTest extends TestCase
{
    public function testAnAdmissionFollowsTheVersionItWasDecidedOn(): void
    {
        $scannedAt = new DateTimeImmutable('2026-11-08 10:00');
        $recordedAt = new DateTimeImmutable('2026-11-08 10:05');
        $checkIn = CheckIn::decided(ScanId::of('s1'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('d1'), $scannedAt, $recordedAt, Attendance::restore(4, 1, false, $scannedAt));

        self::assertSame('s1', $checkIn->id->value);
        self::assertSame('T-1', $checkIn->credentialId);
        self::assertSame('hall', $checkIn->checkpoint->value);
        self::assertSame(Direction::In, $checkIn->direction);
        self::assertSame(ScanOutcome::Admitted, $checkIn->outcome);
        self::assertSame('d1', $checkIn->device->value);
        self::assertSame($scannedAt, $checkIn->scannedAt);
        self::assertSame($recordedAt, $checkIn->recordedAt);
        self::assertSame(5, $checkIn->sequence);
        self::assertFalse($checkIn->offline);
        self::assertNull($checkIn->conflict);
    }

    public function testARefusalHasNoSequence(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00');
        $checkIn = CheckIn::decided(ScanId::of('s1'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Expired, DeviceId::of('d1'), $at, $at, Attendance::restore(4, 1, false, $at));

        self::assertNull($checkIn->sequence);
    }

    public function testAnOfflineScanKeepsItsConflict(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00');
        $checkIn = CheckIn::decided(ScanId::of('s1'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('d1'), $at, $at, Attendance::none(), true, ScanOutcome::AlreadyAdmitted);

        self::assertTrue($checkIn->offline);
        self::assertSame(ScanOutcome::AlreadyAdmitted, $checkIn->conflict);
        self::assertSame(1, $checkIn->sequence);
        $restored = CheckIn::restore(ScanId::of('s1'), 'T-1', CheckpointId::of('hall'), Direction::Out, ScanOutcome::CheckedOut, DeviceId::of('d1'), $at, $at, 1);
        self::assertSame(1, $restored->sequence);
        self::assertFalse($restored->offline);
        self::assertNull($restored->conflict);
    }

    /**
     * @return iterable<string, array{Direction, ScanOutcome, ?int, bool, ?ScanOutcome, string}>
     */
    public static function impossible(): iterable
    {
        $known = 'A check-in is the scan of a known credential.';
        $direction = 'An admission is an entry and a check-out an exit.';
        $sequence = 'An admission or a check-out has a sequence from 1, a refusal none.';

        yield 'an unknown code' => [Direction::In, ScanOutcome::Unknown, null, false, null, $known];
        yield 'an unknown conflict' => [Direction::In, ScanOutcome::Expired, null, true, ScanOutcome::Unknown, $known];
        yield 'a collision' => [Direction::In, ScanOutcome::Collision, null, false, null, $known];
        yield 'a collision as conflict' => [Direction::In, ScanOutcome::Expired, null, true, ScanOutcome::Collision, $known];
        yield 'an admission out' => [Direction::Out, ScanOutcome::Admitted, 1, false, null, $direction];
        yield 'a check-out in' => [Direction::In, ScanOutcome::CheckedOut, 1, false, null, $direction];
        yield 'an admission without sequence' => [Direction::In, ScanOutcome::Admitted, null, false, null, $sequence];
        yield 'a refusal with a sequence' => [Direction::In, ScanOutcome::Expired, 1, false, null, $sequence];
        yield 'a sequence of zero' => [Direction::In, ScanOutcome::Admitted, 0, false, null, $sequence];
        yield 'an online conflict' => [Direction::In, ScanOutcome::Admitted, 1, false, ScanOutcome::AlreadyAdmitted, 'Only a scan made offline can conflict with the ledger.'];
    }

    #[DataProvider('impossible')]
    public function testAnImpossibleCheckInIsRefused(Direction $direction, ScanOutcome $outcome, ?int $sequence, bool $offline, ?ScanOutcome $conflict, string $message): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00');

        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains($message);

        CheckIn::restore(ScanId::of('s1'), 'T-1', CheckpointId::of('hall'), $direction, $outcome, DeviceId::of('d1'), $at, $at, $sequence, $offline, $conflict);
    }
}
