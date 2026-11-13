<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\Offline\OfflineScan;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\CheckinBundle\Application\Event\CheckInRecorded;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckInRecorded::class)]
final class CheckInRecordedTest extends TestCase
{
    public function testAScanDecidedOnlineCarriesItsIdentifiers(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00 UTC');
        $checkIn = CheckIn::decided(ScanId::of('s1'), Credential::of('T-1', 'Élise'), CheckpointId::of('hall'), Direction::Out, ScanOutcome::CheckedOut, DeviceId::of('d1'), $at, $at, Attendance::none());

        $event = CheckInRecorded::online($checkIn);

        self::assertSame(['s1', 'T-1', 'hall', 'd1', Direction::Out, ScanOutcome::CheckedOut, false, false], [$event->id->value, $event->credentialId, $event->checkpoint->value, $event->device->value, $event->direction, $event->outcome, $event->offline, $event->conflict]);
        self::assertTrue($event->changesAttendance());
    }

    public function testAScanSynchronisedSaysWhetherTheServerDecidedOtherwise(): void
    {
        $scan = OfflineScan::of(ScanId::of('s2'), 'T-2', Direction::In, ScanOutcome::Expired, new DateTimeImmutable('2026-11-08 09:00 UTC'));

        $event = CheckInRecorded::synchronised($scan, CheckpointId::of('bar'), DeviceId::of('d2'), true);

        self::assertSame(['s2', 'T-2', 'bar', 'd2', Direction::In, ScanOutcome::Expired, true, true], [$event->id->value, $event->credentialId, $event->checkpoint->value, $event->device->value, $event->direction, $event->outcome, $event->offline, $event->conflict]);
        self::assertFalse($event->changesAttendance());
        self::assertFalse(new CheckInRecorded(ScanId::of('s3'), 'T-3', CheckpointId::of('bar'), DeviceId::of('d2'), Direction::In, ScanOutcome::Admitted)->offline);
    }
}
