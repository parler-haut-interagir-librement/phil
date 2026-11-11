<?php

declare(strict_types=1);

namespace Phil\Checkin\Tests\Unit;

use Phil\Checkin\CredentialCode;
use Phil\Checkin\Exception\InvalidCheckin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(CredentialCode::class)]
final class CredentialCodeTest extends TestCase
{
    #[TestWith(['T-1001'])]
    #[TestWith(['https://example.com/t/abc?x=1 y'])]
    #[TestWith(['Billet n° 7 — Québec'])]
    public function testAValidCodeIsKeptAsItIs(string $value): void
    {
        self::assertSame($value, CredentialCode::of($value)->value);
    }

    public function testACodeOfTheMaximumLengthInBytesIsKept(): void
    {
        self::assertSame(512, CredentialCode::MAX_LENGTH);
        self::assertSame(str_repeat('é', 256), CredentialCode::of(str_repeat('é', 256))->value);
    }

    public function testACodeOverTheMaximumLengthInBytesIsRefused(): void
    {
        $this->expectException(InvalidCheckin::class);

        CredentialCode::of(str_repeat('é', 256).'a');
    }

    #[TestWith([''])]
    #[TestWith(["T-1001\n"])]
    #[TestWith(["T-\t1001"])]
    #[TestWith(["\x7F"])]
    #[TestWith(["\xC3\x28"])]
    public function testAnInvalidCodeIsRefused(string $value): void
    {
        $this->expectException(InvalidCheckin::class);
        $this->expectExceptionMessageIsOrContains('A credential code is valid UTF-8 without control characters, 1 to 512 bytes.');

        CredentialCode::of($value);
    }

    #[TestWith(['0', 3])]
    #[TestWith(['12345678', 26])]
    #[TestWith(['1234567890123456789', 63])]
    #[TestWith(['18446744073709551615', 66])]
    #[TestWith(['1a', 8])]
    #[TestWith(['0123456789abcdef', 64])]
    #[TestWith(['0123456789ABCDEF', 64])]
    #[TestWith(['a1g', 15])]
    #[TestWith(['A1G', 15])]
    #[TestWith(['aB', 12])]
    #[TestWith(['A1g', 18])]
    #[TestWith(['1-', 12])]
    #[TestWith(['1_', 12])]
    #[TestWith(['1 ', 13])]
    #[TestWith(['é', 13])]
    public function testTheStrengthIsEstimatedFromTheLengthAndTheAlphabet(string $value, int $bits): void
    {
        self::assertSame($bits, CredentialCode::of($value)->estimatedBits());
    }

    public function testTheFingerprintIsTheKeyedHmacSha256(): void
    {
        $code = CredentialCode::of('T-1001');

        self::assertSame(hash_hmac('sha256', 'T-1001', 'key'), $code->fingerprint('key'));
        self::assertNotSame($code->fingerprint('key'), $code->fingerprint('other'));
    }
}
