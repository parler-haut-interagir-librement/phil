<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidOption;
use Phil\IpAnonymizer\FixedSalt;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\IpDigest;
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\RotatingSalt;
use Phil\IpAnonymizer\Salt;
use Phil\IpAnonymizer\Tests\Unit\Fixture\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyedHash::class)]
#[CoversClass(IpDigest::class)]
final class KeyedHashTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';

    public function testDigestsAnAddressWithTheSalt(): void
    {
        $hash = new KeyedHash(new FixedSalt(Salt::of('s1', self::KEY)));
        $address = IpAddress::parse('192.0.2.1');
        $expected = 's1:'.bin2hex(sodium_crypto_generichash("\xC0\x00\x02\x01", self::KEY, 16));

        self::assertSame(16, $hash->length);
        self::assertSame($expected, $hash->anonymize($address));
        self::assertSame($expected, (string) $hash->digest($address));
        self::assertSame('s1', $hash->digest($address)->saltId);
    }

    public function testGivesAMappedAddressTheDigestOfItsIpv4Address(): void
    {
        $hash = new KeyedHash(new FixedSalt(Salt::of('s1', self::KEY)));

        self::assertSame($hash->anonymize(IpAddress::parse('192.0.2.1')), $hash->anonymize(IpAddress::parse('::ffff:192.0.2.1')));
        self::assertNotSame($hash->anonymize(IpAddress::parse('192.0.2.1')), $hash->anonymize(IpAddress::parse('192.0.2.2')));
    }

    public function testChangesWithTheSalt(): void
    {
        $clock = new FrozenClock();
        $hash = new KeyedHash(new RotatingSalt(self::KEY, $clock));
        $address = IpAddress::parse('2001:db8::1');
        $clock->set('2025-11-08T10:00:00+00:00');
        $today = $hash->digest($address);
        $clock->set('2025-11-09T10:00:00+00:00');
        $tomorrow = $hash->digest($address);

        self::assertFalse($today->equals($tomorrow));
        self::assertNotSame($today->bytes, $tomorrow->bytes);
    }

    public function testDigestsOfTheLengthItIsGiven(): void
    {
        $salts = new FixedSalt(Salt::of('s', self::KEY));
        $address = IpAddress::parse('192.0.2.1');

        self::assertSame(64, \strlen(new KeyedHash($salts, 64)->digest($address)->bytes));
        self::assertSame(32, \strlen(new KeyedHash($salts, 32)->digest($address)->bytes));
    }

    public function testRefusesADigestTooShort(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('A digest is 16 to 64 bytes long; 15 is not.');

        new KeyedHash(new FixedSalt(Salt::of('s', self::KEY)), 15);
    }

    public function testRefusesADigestTooLong(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('A digest is 16 to 64 bytes long; 65 is not.');

        new KeyedHash(new FixedSalt(Salt::of('s', self::KEY)), 65);
    }

    public function testComparesDigests(): void
    {
        self::assertTrue(new IpDigest('a', 'xyz')->equals(new IpDigest('a', 'xyz')));
        self::assertFalse(new IpDigest('a', 'xyz')->equals(new IpDigest('b', 'xyz')));
        self::assertFalse(new IpDigest('a', 'xyz')->equals(new IpDigest('a', 'xyw')));
    }
}
