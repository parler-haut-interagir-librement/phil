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
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryCheckInLedger::class)]
final class InMemoryCheckInLedgerTest extends TestCase
{
    public function testTheLedgerKeepsTheCheckInsAndTheAttendances(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $admitted = $this->checkIn('s1', 'T-1', ScanOutcome::Admitted, Attendance::none());
        $refused = $this->checkIn('s2', 'T-1', ScanOutcome::AlreadyAdmitted, Attendance::none());

        self::assertTrue($ledger->append($admitted));
        self::assertTrue($ledger->append($refused));
        self::assertSame($admitted, $ledger->find(ScanId::of('s1')));
        self::assertNull($ledger->find(ScanId::of('s3')));
        self::assertSame([$admitted, $refused], $ledger->all());
        self::assertSame(1, $ledger->attendance('T-1')->entries);
        self::assertSame(0, $ledger->attendance('T-2')->version);

        $attendances = $ledger->attendances(['T-1', 'T-2']);
        self::assertSame(['T-1', 'T-2'], array_keys($attendances));
        self::assertSame(1, $attendances['T-1']->version);
        self::assertSame(0, $attendances['T-2']->version);
    }

    public function testAScanIdentifierIsRecordedOnce(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $ledger->append($this->checkIn('s1', 'T-1', ScanOutcome::Expired, Attendance::none()));

        self::assertFalse($ledger->append($this->checkIn('s1', 'T-2', ScanOutcome::Expired, Attendance::none())));
        self::assertCount(1, $ledger->all());
    }

    public function testACheckInDecidedOnAnOlderVersionIsRefused(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $ledger->append($this->checkIn('s1', 'T-1', ScanOutcome::Admitted, Attendance::none()));

        self::assertFalse($ledger->append($this->checkIn('s2', 'T-1', ScanOutcome::Admitted, Attendance::none())));
        self::assertFalse($ledger->append($this->checkIn('s3', 'T-1', ScanOutcome::Admitted, Attendance::restore(2, 0, false, null))));
        self::assertTrue($ledger->append($this->checkIn('s4', 'T-1', ScanOutcome::Admitted, $ledger->attendance('T-1'))));
        self::assertSame(2, $ledger->attendance('T-1')->entries);
    }

    private function checkIn(string $id, string $credential, ScanOutcome $outcome, Attendance $seen): CheckIn
    {
        $at = new DateTimeImmutable('2026-11-08 10:00');

        return CheckIn::decided(ScanId::of($id), Credential::of($credential, maxEntries: null), CheckpointId::of('hall'), Direction::In, $outcome, DeviceId::of('d1'), $at, $at, $seen);
    }
}
