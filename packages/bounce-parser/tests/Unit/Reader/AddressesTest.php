<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit\Reader;

use Phil\BounceParser\Reader\Addresses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Addresses::class)]
final class AddressesTest extends TestCase
{
    public function testReadsTheAddressOfAField(): void
    {
        self::assertSame('Ada@example.com', Addresses::ofTypedField('rfc822; <Ada@Example.COM>'));
        self::assertSame('ada@example.com', Addresses::ofTypedField('ada@example.com'));
        self::assertNull(Addresses::ofTypedField('rfc822; @example.com'));
        self::assertNull(Addresses::ofTypedField('rfc822; ada@'));
        self::assertNull(Addresses::ofTypedField('rfc822; ada'));
        self::assertNull(Addresses::ofTypedField(null));
        self::assertSame('a@b', Addresses::ofTypedField('rfc822; a@b'));
    }

    public function testReadsTheAddressOfAHeader(): void
    {
        self::assertSame('ada@example.com', Addresses::ofHeader('"Ada, L." <ada@Example.com>'));
        self::assertSame('ada@example.com', Addresses::ofHeader(' ada@example.com '));
        self::assertNull(Addresses::ofHeader('Ada <>'));
        self::assertNull(Addresses::ofHeader(null));
    }

    public function testKnowsTheAddressesOfMailServers(): void
    {
        self::assertTrue(Addresses::isDaemon('MAILER-DAEMON@example.com'));
        self::assertTrue(Addresses::isDaemon('mail-daemon'));
        self::assertTrue(Addresses::isDaemon('Postmaster@example.com'));
        self::assertFalse(Addresses::isDaemon('notpostmaster@example.com'));
        self::assertFalse(Addresses::isDaemon('postmasters@example.com'));
    }
}
