<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit\Offline;

use DateTimeImmutable;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Credential;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Exception\LedgerContention;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\Offline\OfflineScan;
use Phil\Checkin\Offline\OfflineSync;
use Phil\Checkin\Offline\SyncReport;
use Phil\Checkin\Offline\SyncStatus;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\Checkin\Tests\Unit\Fixture\FixedCredentials;
use Phil\Checkin\Tests\Unit\Fixture\FrozenClock;
use Phil\Checkin\Tests\Unit\Fixture\ScriptedLedger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(OfflineSync::class)]
#[CoversClass(SyncReport::class)]
final class OfflineSyncTest extends TestCase
{
    private FixedCredentials $catalog;

    private FrozenClock $clock;

    private CheckpointId $hall;

    private DeviceId $device;

    protected function setUp(): void
    {
        $this->catalog = new FixedCredentials([
            'CODE-1' => Credential::of('T-1'),
            'CODE-2' => Credential::of('T-2', maxEntries: null),
        ]);
        $this->clock = new FrozenClock();
        $this->hall = CheckpointId::of('hall');
        $this->device = DeviceId::of('door-1');
    }

    public function testTheScansAreRecordedByInstantThenByIdentifierWithTheDecisionOfTheDevice(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $report = $this->sync($ledger)->apply($this->hall, $this->device, [
            $this->scan('c', 'T-2', -60, Direction::In, ScanOutcome::Admitted),
            $this->scan('b', 'T-2', -120, Direction::Out, ScanOutcome::CheckedOut),
            $this->scan('a', 'T-2', -120, Direction::In, ScanOutcome::Admitted),
        ]);

        self::assertSame([['id' => 'a', 'status' => 'recorded'], ['id' => 'b', 'status' => 'recorded'], ['id' => 'c', 'status' => 'recorded']], $report->toArray());
        self::assertSame(3, $report->count(SyncStatus::Recorded));
        self::assertSame(0, $report->count(SyncStatus::Conflict));

        $recorded = $ledger->all();
        self::assertSame(['a', 'b', 'c'], array_map(static fn (CheckIn $checkIn): string => $checkIn->id->value, $recorded));
        self::assertTrue($recorded[0]->offline);
        self::assertNull($recorded[0]->conflict);
        self::assertSame('door-1', $recorded[0]->device->value);
        self::assertSame('hall', $recorded[0]->checkpoint->value);
        self::assertSame($this->clock->now()->getTimestamp() - 120, $recorded[0]->scannedAt->getTimestamp());
        self::assertSame($this->clock->now(), $recorded[0]->recordedAt);
        self::assertSame([1, 2, 3], array_map(static fn (CheckIn $checkIn): ?int => $checkIn->sequence, $recorded));
        self::assertSame(2, $ledger->attendance('T-2')->entries);
    }

    public function testAnEntryTwoDevicesAdmittedIsRecordedAsAConflict(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $sync = $this->sync($ledger);
        $sync->apply($this->hall, DeviceId::of('door-2'), [$this->scan('a', 'T-1', -120, Direction::In, ScanOutcome::Admitted)]);

        $report = $sync->apply($this->hall, $this->device, [$this->scan('b', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame([['id' => 'b', 'status' => 'conflict']], $report->toArray());
        self::assertSame(1, $report->count(SyncStatus::Conflict));
        self::assertSame(ScanOutcome::Admitted, $ledger->all()[1]->outcome);
        self::assertSame(ScanOutcome::AlreadyAdmitted, $ledger->all()[1]->conflict);
        self::assertSame(2, $ledger->attendance('T-1')->entries);
    }

    public function testARefusalTheLedgerWouldHaveAdmittedIsAConflictAndChangesNothing(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $report = $this->sync($ledger)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::AlreadyAdmitted)]);

        self::assertSame(1, $report->count(SyncStatus::Conflict));
        self::assertSame(ScanOutcome::Admitted, $ledger->all()[0]->conflict);
        self::assertSame(0, $ledger->attendance('T-1')->entries);
    }

    public function testThePolicyDecidesAtTheInstantOfTheScan(): void
    {
        $catalog = new FixedCredentials(['CODE-1' => Credential::of('T-1', validUntil: $this->clock->now()->modify('-90 seconds'))]);
        $ledger = new InMemoryCheckInLedger();
        $report = new OfflineSync($catalog, $ledger, $this->clock, new AdmissionPolicy())->apply($this->hall, $this->device, [
            $this->scan('a', 'T-1', -120, Direction::In, ScanOutcome::Admitted),
            $this->scan('b', 'T-1', -60, Direction::Out, ScanOutcome::CheckedOut),
        ]);

        self::assertSame(2, $report->count(SyncStatus::Recorded));
    }

    public function testAScanAlreadyRecordedIsADuplicate(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $sync = $this->sync($ledger);
        $sync->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        $report = $sync->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame([['id' => 'a', 'status' => 'duplicate']], $report->toArray());
        self::assertSame(1, $report->count(SyncStatus::Duplicate));
        self::assertCount(1, $ledger->all());
    }

    /**
     * @return iterable<string, array{string, string, string, Direction, ScanOutcome}>
     */
    public static function collisions(): iterable
    {
        yield 'another credential' => ['T-2', 'hall', 'door-1', Direction::In, ScanOutcome::Admitted];
        yield 'another checkpoint' => ['T-1', 'stage', 'door-1', Direction::In, ScanOutcome::Admitted];
        yield 'another device' => ['T-1', 'hall', 'door-2', Direction::In, ScanOutcome::Admitted];
        yield 'another direction' => ['T-1', 'hall', 'door-1', Direction::Out, ScanOutcome::NotInside];
        yield 'another outcome' => ['T-1', 'hall', 'door-1', Direction::In, ScanOutcome::AlreadyAdmitted];
    }

    #[DataProvider('collisions')]
    public function testAScanIdentifierRecordedForAnotherScanIsACollision(string $credential, string $checkpoint, string $device, Direction $direction, ScanOutcome $outcome): void
    {
        $ledger = new InMemoryCheckInLedger();
        $sync = $this->sync($ledger);
        $sync->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        $report = $sync->apply(CheckpointId::of($checkpoint), DeviceId::of($device), [$this->scan('a', $credential, -60, $direction, $outcome)]);

        self::assertSame([['id' => 'a', 'status' => 'collision']], $report->toArray());
        self::assertSame(1, $report->count(SyncStatus::Collision));
        self::assertCount(1, $ledger->all());
    }

    public function testAScanIdentifierRecordedOnlineIsACollision(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $at = $this->clock->now()->modify('-60 seconds');
        $ledger->append(CheckIn::decided(ScanId::of('a'), Credential::of('T-1'), $this->hall, Direction::In, ScanOutcome::Admitted, $this->device, $at, $at, Attendance::none()));

        $report = $this->sync($ledger)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame(1, $report->count(SyncStatus::Collision));
    }

    public function testARaceWithAnotherScanUnderTheSameIdentifierIsACollision(): void
    {
        $other = static function (InMemoryCheckInLedger $inner): void {
            $at = new DateTimeImmutable('2026-11-08T09:59:00+00:00');
            $inner->append(CheckIn::decided(ScanId::of('a'), Credential::of('T-2'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('door-1'), $at, $at, Attendance::none(), true));
        };
        $ledger = new ScriptedLedger(before: [$other]);
        $report = $this->sync($ledger)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame(1, $report->count(SyncStatus::Collision));
        self::assertSame(1, $ledger->appends);
    }

    public function testADuplicateIsNotDecidedAgain(): void
    {
        $ledger = new ScriptedLedger();
        $this->sync($ledger->inner)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);
        $this->clock->advance(172_800);
        $report = $this->sync($ledger)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -172_860, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame(1, $report->count(SyncStatus::Duplicate));
        self::assertSame(0, $ledger->appends);
    }

    public function testAnInstantOfNowIsKeptAsTheDeviceGaveIt(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $scan = $this->scan('a', 'T-1', 0, Direction::In, ScanOutcome::Admitted);
        $this->sync($ledger)->apply($this->hall, $this->device, [$scan]);

        self::assertSame($scan->scannedAt, $ledger->all()[0]->scannedAt);
    }

    public function testAScanOfACredentialNoLongerKnownIsRejected(): void
    {
        $report = $this->sync(new InMemoryCheckInLedger())->apply($this->hall, $this->device, [$this->scan('a', 'T-9', -60, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame([['id' => 'a', 'status' => 'rejected']], $report->toArray());
    }

    public function testTheInstantOfAScanIsBounded(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $report = new OfflineSync($this->catalog, $ledger, $this->clock, skewSeconds: 30, maxAgeSeconds: 3600)->apply($this->hall, $this->device, [
            $this->scan('old', 'T-2', -3601, Direction::In, ScanOutcome::Admitted),
            $this->scan('oldest-kept', 'T-2', -3600, Direction::In, ScanOutcome::Admitted),
            $this->scan('ahead', 'T-2', 30, Direction::Out, ScanOutcome::CheckedOut),
            $this->scan('too-far-ahead', 'T-2', 31, Direction::In, ScanOutcome::Admitted),
        ]);

        self::assertSame([
            ['id' => 'old', 'status' => 'rejected'],
            ['id' => 'oldest-kept', 'status' => 'recorded'],
            ['id' => 'ahead', 'status' => 'recorded'],
            ['id' => 'too-far-ahead', 'status' => 'rejected'],
        ], $report->toArray());
        self::assertSame(2, $report->count(SyncStatus::Rejected));
        self::assertSame($this->clock->now(), $ledger->all()[1]->scannedAt);
    }

    public function testTheDefaultBoundsAreFiveMinutesAheadAndTwoDaysOld(): void
    {
        $report = $this->sync(new InMemoryCheckInLedger())->apply($this->hall, $this->device, [
            $this->scan('a', 'T-2', -172_800, Direction::In, ScanOutcome::Admitted),
            $this->scan('b', 'T-2', -172_801, Direction::In, ScanOutcome::Admitted),
            $this->scan('c', 'T-2', 300, Direction::Out, ScanOutcome::CheckedOut),
            $this->scan('d', 'T-2', 301, Direction::In, ScanOutcome::Admitted),
        ]);

        self::assertSame(['recorded', 'rejected', 'recorded', 'rejected'], array_map(static fn (array $entry): string => $entry['status'], [$report->toArray()[1], $report->toArray()[0], $report->toArray()[2], $report->toArray()[3]]));
    }

    public function testAScanRaceIsDecidedAgain(): void
    {
        $other = static function (InMemoryCheckInLedger $inner): void {
            $at = new DateTimeImmutable('2026-11-08T09:59:00+00:00');
            $inner->append(CheckIn::decided(ScanId::of('online'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('door-2'), $at, $at, Attendance::none()));
        };
        $ledger = new ScriptedLedger(before: [$other]);
        $report = $this->sync($ledger)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame(1, $report->count(SyncStatus::Conflict));
        self::assertSame(2, $ledger->appends);
    }

    public function testARaceWithTheSameScanIsADuplicate(): void
    {
        $same = static function (InMemoryCheckInLedger $inner): void {
            $at = new DateTimeImmutable('2026-11-08T09:59:00+00:00');
            $inner->append(CheckIn::decided(ScanId::of('a'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('door-1'), $at, $at, Attendance::none(), true));
        };
        $ledger = new ScriptedLedger(before: [$same]);
        $report = $this->sync($ledger)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);

        self::assertSame(1, $report->count(SyncStatus::Duplicate));
        self::assertSame(1, $ledger->appends);
    }

    public function testTheSynchronisationGivesUpAfterItsAttempts(): void
    {
        $ledger = new ScriptedLedger(refuseAll: true);

        try {
            $this->sync($ledger)->apply($this->hall, $this->device, [$this->scan('a', 'T-1', -60, Direction::In, ScanOutcome::Admitted)]);
            self::fail('The synchronisation gives up.');
        } catch (LedgerContention) {
            self::assertSame(OfflineSync::ATTEMPTS, $ledger->appends);
            self::assertSame(3, OfflineSync::ATTEMPTS);
        }
    }

    public function testTheExtremeBoundsAreKept(): void
    {
        $this->expectNotToPerformAssertions();

        new OfflineSync($this->catalog, new InMemoryCheckInLedger(), $this->clock, skewSeconds: 0, maxAgeSeconds: 60);
        new OfflineSync($this->catalog, new InMemoryCheckInLedger(), $this->clock, skewSeconds: 3600, maxAgeSeconds: 2_592_000);
    }

    #[TestWith([-1, 60, 'A device clock may be 0 to 3600 seconds ahead.'])]
    #[TestWith([3601, 60, 'A device clock may be 0 to 3600 seconds ahead.'])]
    #[TestWith([0, 59, 'A scan made offline may be 60 to 2592000 seconds old.'])]
    #[TestWith([0, 2_592_001, 'A scan made offline may be 60 to 2592000 seconds old.'])]
    public function testInvalidBoundsAreRefused(int $skew, int $maxAge, string $message): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains($message);

        new OfflineSync($this->catalog, new InMemoryCheckInLedger(), $this->clock, skewSeconds: $skew, maxAgeSeconds: $maxAge);
    }

    private function sync(CheckInLedger $ledger): OfflineSync
    {
        return new OfflineSync($this->catalog, $ledger, $this->clock);
    }

    private function scan(string $id, string $credential, int $offset, Direction $direction, ScanOutcome $outcome): OfflineScan
    {
        return OfflineScan::of(ScanId::of($id), $credential, $direction, $outcome, $this->clock->now()->modify(\sprintf('%+d seconds', $offset)));
    }
}
