<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use DateTimeImmutable;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\Checkin\CredentialStatus;
use Phil\Checkin\Exception\InvalidCheckin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Credential::class)]
final class CredentialTest extends TestCase
{
    public function testACredentialOpensEveryCheckpointOnceWithoutLimitOfTimeByDefault(): void
    {
        $credential = Credential::of('T-1001');

        self::assertSame('T-1001', $credential->id);
        self::assertNull($credential->label);
        self::assertSame([], $credential->checkpoints);
        self::assertNull($credential->validFrom);
        self::assertNull($credential->validUntil);
        self::assertSame(1, $credential->maxEntries);
        self::assertSame(CredentialStatus::Active, $credential->status);
        self::assertTrue($credential->opens(CheckpointId::of('anywhere')));
    }

    public function testACredentialKeepsWhatItIsGiven(): void
    {
        $from = new DateTimeImmutable('2026-11-08 09:00');
        $until = new DateTimeImmutable('2026-11-08 09:00');
        $credential = Credential::of('T-1', 'Élise Tremblay', [CheckpointId::of('hall')], $from, $until, null, CredentialStatus::Revoked);

        self::assertSame('Élise Tremblay', $credential->label);
        self::assertSame($from, $credential->validFrom);
        self::assertSame($until, $credential->validUntil);
        self::assertNull($credential->maxEntries);
        self::assertSame(CredentialStatus::Revoked, $credential->status);
    }

    public function testACredentialOpensOnlyTheCheckpointsItLists(): void
    {
        $credential = Credential::of('T-1', checkpoints: [CheckpointId::of('hall'), CheckpointId::of('stage')]);

        self::assertTrue($credential->opens(CheckpointId::of('hall')));
        self::assertTrue($credential->opens(CheckpointId::of('stage')));
        self::assertFalse($credential->opens(CheckpointId::of('backstage')));
    }

    public function testTheBoundsAreKept(): void
    {
        self::assertSame(str_repeat('x', 128), Credential::of(str_repeat('x', 128))->id);
        self::assertSame(str_repeat('é', 200), Credential::of('a', str_repeat('é', 200))->label);
        self::assertSame(1_000_000, Credential::of('a', maxEntries: 1_000_000)->maxEntries);
        self::assertSame('!~', Credential::of('!~')->id);
    }

    #[TestWith([''])]
    #[TestWith(['T 1'])]
    #[TestWith(["T-1\n"])]
    #[TestWith(['T-é'])]
    public function testAnInvalidIdentifierIsRefused(string $id): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('A credential identifier is 1 to 128 printable ASCII characters.');

        Credential::of($id);
    }

    public function testATooLongIdentifierIsRefused(): void
    {
        $this->expectException(InvalidCheckin::class);

        Credential::of(str_repeat('x', 129));
    }

    #[TestWith([''])]
    #[TestWith(["Élise\n"])]
    #[TestWith(["\xC3\x28"])]
    public function testAnInvalidLabelIsRefused(string $label): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('A credential label is valid UTF-8 without control characters, 1 to 200 characters.');

        Credential::of('a', $label);
    }

    public function testATooLongLabelIsRefused(): void
    {
        $this->expectException(InvalidCheckin::class);

        Credential::of('a', str_repeat('é', 201));
    }

    public function testACredentialCannotCloseBeforeItOpens(): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('A credential cannot close before it opens.');

        Credential::of('a', validFrom: new DateTimeImmutable('2026-11-08 09:00:01'), validUntil: new DateTimeImmutable('2026-11-08 09:00:00'));
    }

    public function testOnlyOneBoundOfTimeIsEnough(): void
    {
        self::assertNull(Credential::of('a', validFrom: new DateTimeImmutable('2026-11-08'))->validUntil);
        self::assertNull(Credential::of('a', validUntil: new DateTimeImmutable('2026-11-08'))->validFrom);
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    #[TestWith([1_000_001])]
    public function testAnInvalidNumberOfEntriesIsRefused(int $entries): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('A credential allows 1 to 1000000 entries, or any number.');

        Credential::of('a', maxEntries: $entries);
    }
}
