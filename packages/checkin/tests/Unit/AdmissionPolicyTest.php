<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use DateTimeImmutable;
use Phil\Checkin\AdmissionPolicy;
use Phil\Checkin\Attendance;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialStatus;
use Phil\Checkin\Direction;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\ScanOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdmissionPolicy::class)]
final class AdmissionPolicyTest extends TestCase
{
    private CheckpointId $hall;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->hall = CheckpointId::of('hall');
        $this->now = new DateTimeImmutable('2026-11-08 10:00:00');
    }

    public function testThePolicyLetsNobodyInEarlyAndForbidsPassingBackByDefault(): void
    {
        $policy = new AdmissionPolicy();

        self::assertSame(0, $policy->earlySeconds);
        self::assertTrue($policy->antiPassback);
    }

    #[TestWith([-1])]
    #[TestWith([86_401])]
    public function testAnInvalidEarlinessIsRefused(int $seconds): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('A credential is let in 0 to 86400 seconds before it opens.');

        new AdmissionPolicy($seconds);
    }

    public function testTheBoundsOfEarlinessAreKept(): void
    {
        self::assertSame(86_400, new AdmissionPolicy(86_400)->earlySeconds);
    }

    public function testAFreshCredentialIsAdmitted(): void
    {
        self::assertSame(ScanOutcome::Admitted, $this->decide(Credential::of('T-1')));
    }

    public function testARevokedCredentialIsRefusedBeforeAnythingElse(): void
    {
        $revoked = Credential::of('T-1', checkpoints: [CheckpointId::of('stage')], status: CredentialStatus::Revoked);

        self::assertSame(ScanOutcome::Revoked, $this->decide($revoked));
        self::assertSame(ScanOutcome::Revoked, $this->decide($revoked, direction: Direction::Out));
    }

    public function testACheckpointTheCredentialDoesNotOpenIsRefused(): void
    {
        $stage = Credential::of('T-1', checkpoints: [CheckpointId::of('stage')], validUntil: new DateTimeImmutable('2026-11-07'));

        self::assertSame(ScanOutcome::WrongCheckpoint, $this->decide($stage));
        self::assertSame(ScanOutcome::WrongCheckpoint, $this->decide($stage, direction: Direction::Out));
    }

    public function testACheckOutLetsOutOnlyAHolderInside(): void
    {
        $closed = Credential::of('T-1', validUntil: new DateTimeImmutable('2026-11-07'));
        $inside = Attendance::restore(1, 1, true, new DateTimeImmutable('2026-11-07'));

        self::assertSame(ScanOutcome::CheckedOut, $this->decide($closed, $inside, Direction::Out));
        self::assertSame(ScanOutcome::NotInside, $this->decide($closed, Attendance::none(), Direction::Out));
    }

    public function testACredentialNotYetOpenIsRefusedUntilTheEarlyWindow(): void
    {
        $credential = Credential::of('T-1', validFrom: new DateTimeImmutable('2026-11-08 10:15:00'));

        self::assertSame(ScanOutcome::NotYetValid, $this->decide($credential));
        self::assertSame(ScanOutcome::NotYetValid, $this->decide($credential, policy: new AdmissionPolicy(899)));
        self::assertSame(ScanOutcome::Admitted, $this->decide($credential, policy: new AdmissionPolicy(900)));
        self::assertSame(ScanOutcome::Admitted, $this->decide(Credential::of('T-1', validFrom: $this->now)));
    }

    public function testAClosedCredentialIsRefusedAfterItsLastSecond(): void
    {
        self::assertSame(ScanOutcome::Admitted, $this->decide(Credential::of('T-1', validUntil: $this->now)));
        self::assertSame(ScanOutcome::Expired, $this->decide(Credential::of('T-1', validUntil: $this->now->modify('-1 second'))));
    }

    public function testAHolderInsideComesInAgainOnlyWithoutAntiPassback(): void
    {
        $unlimited = Credential::of('T-1', maxEntries: null);
        $inside = Attendance::restore(1, 1, true, $this->now);

        self::assertSame(ScanOutcome::AlreadyAdmitted, $this->decide($unlimited, $inside));
        self::assertSame(ScanOutcome::Admitted, $this->decide($unlimited, $inside, policy: new AdmissionPolicy(antiPassback: false)));
        self::assertSame(ScanOutcome::Admitted, $this->decide($unlimited, Attendance::restore(2, 1, false, $this->now)));
    }

    public function testACredentialThatUsedItsEntriesIsRefused(): void
    {
        $twice = Credential::of('T-1', maxEntries: 2);
        $policy = new AdmissionPolicy(antiPassback: false);

        self::assertSame(ScanOutcome::Admitted, $this->decide($twice, Attendance::restore(2, 1, false, $this->now), policy: $policy));
        self::assertSame(ScanOutcome::AlreadyAdmitted, $this->decide($twice, Attendance::restore(3, 2, false, $this->now), policy: $policy));
        self::assertSame(ScanOutcome::AlreadyAdmitted, $this->decide(Credential::of('T-1'), Attendance::restore(2, 1, false, $this->now), policy: $policy));
    }

    private function decide(Credential $credential, ?Attendance $attendance = null, Direction $direction = Direction::In, ?AdmissionPolicy $policy = null): ScanOutcome
    {
        return ($policy ?? new AdmissionPolicy())->decide($credential, $attendance ?? Attendance::none(), $this->hall, $direction, $this->now);
    }
}
