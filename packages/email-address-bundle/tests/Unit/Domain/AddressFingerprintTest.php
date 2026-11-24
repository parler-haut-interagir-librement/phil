<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Domain;

use InvalidArgumentException;
use Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddressFingerprint::class)]
final class AddressFingerprintTest extends TestCase
{
    public function testAFingerprintIsTheKeyedHashOfTheNormalizedAddress(): void
    {
        $fingerprint = AddressFingerprint::of('ada@example.org', 'a key of the application');

        self::assertSame(hash_hmac('sha256', 'ada@example.org', 'a key of the application'), $fingerprint->value);
        self::assertSame($fingerprint->value, (string) $fingerprint);
        self::assertTrue($fingerprint->equals(AddressFingerprint::of('ada@example.org', 'a key of the application')));
        self::assertFalse($fingerprint->equals(AddressFingerprint::of('ada@example.org', 'another key')));
        self::assertFalse($fingerprint->equals(AddressFingerprint::of('grace@example.org', 'a key of the application')));
    }

    public function testAFingerprintNeedsAKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('An address fingerprint needs a key.');

        AddressFingerprint::of('ada@example.org', '');
    }

    public function testAFingerprintIsReadBack(): void
    {
        $value = hash('sha256', 'x');

        self::assertSame($value, AddressFingerprint::fromString($value)->value);
    }

    #[TestWith([''])]
    #[TestWith(['ABCDEF0123456789abcdef0123456789abcdef0123456789abcdef0123456789'])]
    #[TestWith(['0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcde'])]
    #[TestWith(['0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0'])]
    #[TestWith(["0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef\n"])]
    #[TestWith(['x0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'])]
    public function testATextThatIsNoFingerprintIsRefused(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('An address fingerprint is 64 lowercase hexadecimal digits.');

        AddressFingerprint::fromString($value);
    }
}
