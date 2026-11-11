<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use Phil\Checkin\CheckpointId;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Exception\InvalidCheckin;
use Phil\Checkin\Identifier;
use Phil\Checkin\ScanId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(CheckpointId::class)]
#[CoversClass(DeviceId::class)]
#[CoversClass(ScanId::class)]
#[CoversClass(InvalidCheckin::class)]
final class IdentifiersTest extends TestCase
{
    #[TestWith(['a'])]
    #[TestWith(['Main-Door_2:hall.B'])]
    #[TestWith(['0f8fad5b-d9cb-469f-a165-70867728950e'])]
    public function testAValidIdentifierIsKept(string $value): void
    {
        self::assertSame($value, CheckpointId::of($value)->value);
        self::assertSame($value, DeviceId::of($value)->value);
        self::assertSame($value, ScanId::of($value)->value);
    }

    public function testAnIdentifierOfTheMaximumLengthIsKept(): void
    {
        self::assertSame(64, Identifier::MAX_LENGTH);
        self::assertSame(str_repeat('k', 64), CheckpointId::of(str_repeat('k', 64))->value);
    }

    #[TestWith([''])]
    #[TestWith(['a b'])]
    #[TestWith(["door\n"])]
    #[TestWith(['/door'])]
    #[TestWith(['door/'])]
    #[TestWith(['porte-é'])]
    #[TestWith(['kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk'])]
    public function testAnInvalidIdentifierIsRefused(string $value): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('A checkpoint identifier is 1 to 64 letters, digits, ".", "_", ":" or "-".');

        CheckpointId::of($value);
    }

    public function testEachIdentifierNamesWhatItIs(): void
    {
        foreach ([[DeviceId::of(...), 'device identifier'], [ScanId::of(...), 'scan identifier']] as [$of, $what]) {
            try {
                $of('');
                self::fail('An empty identifier is refused.');
            } catch (InvalidCheckin $invalidCheckin) {
                self::assertStringStartsWith('A '.$what.' is', $invalidCheckin->getMessage());
            }
        }
    }

    public function testIdentifiersCompareByValue(): void
    {
        self::assertTrue(CheckpointId::of('a')->equals(CheckpointId::of('a')));
        self::assertFalse(CheckpointId::of('a')->equals(CheckpointId::of('b')));
        self::assertTrue(DeviceId::of('a')->equals(DeviceId::of('a')));
        self::assertFalse(DeviceId::of('a')->equals(DeviceId::of('b')));
        self::assertTrue(ScanId::of('a')->equals(ScanId::of('a')));
        self::assertFalse(ScanId::of('a')->equals(ScanId::of('b')));
    }

    public function testAGeneratedScanIdentifierIs128RandomBits(): void
    {
        $id = ScanId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->value);
        self::assertNotSame($id->value, ScanId::generate()->value);
    }
}
