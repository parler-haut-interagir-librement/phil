<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use DateTimeImmutable;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckInDesk;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialCode;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\LedgerContention;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\Checkin\ScanResult;
use Phil\Checkin\Tests\Unit\Fixture\FixedCredentials;
use Phil\Checkin\Tests\Unit\Fixture\FrozenClock;
use Phil\Checkin\Tests\Unit\Fixture\ScriptedLedger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckInDesk::class)]
#[CoversClass(ScanResult::class)]
#[CoversClass(LedgerContention::class)]
final class CheckInDeskTest extends TestCase
{
    private FixedCredentials $credentials;

    private FrozenClock $clock;

    private CheckpointId $hall;

    private DeviceId $device;

    protected function setUp(): void
    {
        $this->credentials = new FixedCredentials([
            'CODE-1' => Credential::of('T-1', 'Élise'),
            'CODE-2' => Credential::of('T-2', maxEntries: null),
        ]);
        $this->clock = new FrozenClock();
        $this->hall = CheckpointId::of('hall');
        $this->device = DeviceId::of('door-1');
    }

    public function testAnUnknownCodeIsAnsweredAndNothingIsRecorded(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $result = $this->desk($ledger)->scan(CredentialCode::of('NOPE'), $this->hall, $this->device);

        self::assertSame(ScanOutcome::Unknown, $result->outcome);
        self::assertNull($result->credential);
        self::assertNull($result->checkIn);
        self::assertEquals(Attendance::none(), $result->attendance);
        self::assertFalse($result->replayed);
        self::assertSame([], $ledger->all());
    }

    public function testAKnownCodeIsAdmittedAndRecordedAtTheInstantOfTheClock(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $result = $this->desk($ledger)->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device, id: ScanId::of('s1'));

        self::assertSame(ScanOutcome::Admitted, $result->outcome);
        self::assertSame('Élise', $result->credential?->label);
        self::assertSame(1, $result->attendance->entries);
        self::assertTrue($result->attendance->inside);
        self::assertFalse($result->replayed);
        self::assertSame([$result->checkIn], $ledger->all());
        self::assertSame('s1', $result->checkIn?->id->value);
        self::assertSame($this->clock->now(), $result->checkIn->scannedAt);
        self::assertSame($this->clock->now(), $result->checkIn->recordedAt);
        self::assertSame('door-1', $result->checkIn->device->value);
        self::assertSame(Direction::In, $result->checkIn->direction);
        self::assertSame(1, $result->checkIn->sequence);
    }

    public function testASecondScanIsRefusedAndRecorded(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $desk = $this->desk($ledger);
        $desk->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device);

        $this->clock->advance(60);
        $result = $desk->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device);

        self::assertSame(ScanOutcome::AlreadyAdmitted, $result->outcome);
        self::assertSame(1, $result->attendance->entries);
        self::assertEquals($this->clock->now()->modify('-60 seconds'), $result->attendance->lastEntryAt);
        self::assertCount(2, $ledger->all());
        self::assertNotSame($ledger->all()[0]->id->value, $ledger->all()[1]->id->value);
    }

    public function testACheckOutLetsTheHolderComeBackWithAnUnlimitedCredential(): void
    {
        $desk = $this->desk(new InMemoryCheckInLedger());

        self::assertSame(ScanOutcome::Admitted, $desk->scan(CredentialCode::of('CODE-2'), $this->hall, $this->device)->outcome);
        self::assertSame(ScanOutcome::CheckedOut, $desk->scan(CredentialCode::of('CODE-2'), $this->hall, $this->device, Direction::Out)->outcome);
        self::assertSame(ScanOutcome::Admitted, $desk->scan(CredentialCode::of('CODE-2'), $this->hall, $this->device)->outcome);
    }

    public function testThePolicyDecides(): void
    {
        $desk = new CheckInDesk($this->credentials, new InMemoryCheckInLedger(), $this->clock, new AdmissionPolicy(antiPassback: false));
        $desk->scan(CredentialCode::of('CODE-2'), $this->hall, $this->device);

        self::assertSame(ScanOutcome::Admitted, $desk->scan(CredentialCode::of('CODE-2'), $this->hall, $this->device)->outcome);
    }

    public function testAScanSentAgainIsAnsweredWithItsFirstDecision(): void
    {
        $ledger = new InMemoryCheckInLedger();
        $desk = $this->desk($ledger);
        $first = $desk->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device, id: ScanId::of('s1'));
        $this->clock->advance(60);
        $again = $desk->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device, id: ScanId::of('s1'));

        self::assertSame(ScanOutcome::Admitted, $again->outcome);
        self::assertTrue($again->replayed);
        self::assertSame($first->checkIn, $again->checkIn);
        self::assertSame('T-1', $again->credential?->id);
        self::assertSame(1, $again->attendance->entries);
        self::assertCount(1, $ledger->all());
    }

    /**
     * @return iterable<string, array{string, string, string, Direction}>
     */
    public static function collisions(): iterable
    {
        yield 'an unknown code' => ['NOPE', 'hall', 'door-1', Direction::In];
        yield 'another credential' => ['CODE-2', 'hall', 'door-1', Direction::In];
        yield 'another checkpoint' => ['CODE-1', 'stage', 'door-1', Direction::In];
        yield 'another device' => ['CODE-1', 'hall', 'door-2', Direction::In];
        yield 'another direction' => ['CODE-1', 'hall', 'door-1', Direction::Out];
    }

    #[DataProvider('collisions')]
    public function testAScanIdentifierSentAgainWithAnotherScanIsACollisionThatGivesNothingAway(string $code, string $checkpoint, string $device, Direction $direction): void
    {
        $ledger = new InMemoryCheckInLedger();
        $desk = $this->desk($ledger);
        $desk->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device, id: ScanId::of('s1'));

        $result = $desk->scan(CredentialCode::of($code), CheckpointId::of($checkpoint), DeviceId::of($device), $direction, ScanId::of('s1'));

        self::assertSame(ScanOutcome::Collision, $result->outcome);
        self::assertNull($result->credential);
        self::assertNull($result->checkIn);
        self::assertEquals(Attendance::none(), $result->attendance);
        self::assertFalse($result->replayed);
        self::assertCount(1, $ledger->all());
    }

    public function testARaceWithAnotherScanUnderTheSameIdentifierIsACollision(): void
    {
        $other = static function (InMemoryCheckInLedger $inner): void {
            $at = new DateTimeImmutable('2026-11-08T09:59:00+00:00');
            $inner->append(CheckIn::decided(ScanId::of('s1'), Credential::of('T-2'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('door-1'), $at, $at, Attendance::none()));
        };
        $ledger = new ScriptedLedger(before: [$other]);
        $result = $this->desk($ledger)->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device, id: ScanId::of('s1'));

        self::assertSame(ScanOutcome::Collision, $result->outcome);
        self::assertNull($result->checkIn);
        self::assertFalse($result->replayed);
        self::assertSame(1, $ledger->appends);
    }

    public function testAReplayedResultGivesTheCredentialOnlyWhenItIsTheOneRecorded(): void
    {
        $at = new DateTimeImmutable('2026-11-08T09:59:00+00:00');
        $checkIn = CheckIn::decided(ScanId::of('s1'), Credential::of('T-1'), $this->hall, Direction::In, ScanOutcome::Admitted, $this->device, $at, $at, Attendance::none());

        self::assertSame('T-1', ScanResult::replayed($checkIn, Credential::of('T-1'), Attendance::none())->credential?->id);
        self::assertNull(ScanResult::replayed($checkIn, Credential::of('T-2'), Attendance::none())->credential);
        self::assertNull(ScanResult::replayed($checkIn, null, Attendance::none())->credential);
    }

    public function testAScanRaceIsDecidedAgainOnTheNewAttendance(): void
    {
        $other = static function (InMemoryCheckInLedger $inner): void {
            $at = new DateTimeImmutable('2026-11-08T09:59:00+00:00');
            $inner->append(CheckIn::decided(ScanId::of('other'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('door-2'), $at, $at, Attendance::none()));
        };
        $ledger = new ScriptedLedger(before: [$other]);
        $result = $this->desk($ledger)->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device, id: ScanId::of('s1'));

        self::assertSame(ScanOutcome::AlreadyAdmitted, $result->outcome);
        self::assertSame(2, $ledger->appends);
        self::assertFalse($result->replayed);
    }

    public function testARaceWithTheSameScanIsAnsweredAsReplayed(): void
    {
        $same = static function (InMemoryCheckInLedger $inner): void {
            $at = new DateTimeImmutable('2026-11-08T09:59:00+00:00');
            $inner->append(CheckIn::decided(ScanId::of('s1'), Credential::of('T-1'), CheckpointId::of('hall'), Direction::In, ScanOutcome::Admitted, DeviceId::of('door-1'), $at, $at, Attendance::none()));
        };
        $ledger = new ScriptedLedger(before: [$same]);
        $result = $this->desk($ledger)->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device, id: ScanId::of('s1'));

        self::assertTrue($result->replayed);
        self::assertSame(ScanOutcome::Admitted, $result->outcome);
        self::assertSame(1, $result->attendance->entries);
        self::assertSame(1, $ledger->appends);
    }

    public function testTheDeskGivesUpAfterItsAttempts(): void
    {
        $ledger = new ScriptedLedger(refuseAll: true);

        try {
            $this->desk($ledger)->scan(CredentialCode::of('CODE-1'), $this->hall, $this->device);
            self::fail('The desk gives up.');
        } catch (LedgerContention $ledgerContention) {
            self::assertSame('The attendance of the credential changed under each of 3 attempts to record the scan.', $ledgerContention->getMessage());
        }

        self::assertSame(CheckInDesk::ATTEMPTS, $ledger->appends);
        self::assertSame(3, CheckInDesk::ATTEMPTS);
    }

    private function desk(CheckInLedger $ledger): CheckInDesk
    {
        return new CheckInDesk($this->credentials, $ledger, $this->clock);
    }
}
