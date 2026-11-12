<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\IpFamily;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Truncation::class)]
final class TruncationTest extends TestCase
{
    public function testClearsTheHostBitsPastTheDefaultPrefixes(): void
    {
        $truncation = new Truncation();

        self::assertSame(24, $truncation->ipv4Prefix);
        self::assertSame(48, $truncation->ipv6Prefix);
        self::assertSame('192.0.2.0', $truncation->anonymize(IpAddress::parse('192.0.2.123')));
        self::assertSame('2001:db8:1234::', $truncation->anonymize(IpAddress::parse('2001:db8:1234:5678::1')));
        self::assertSame('192.0.2.0', $truncation->anonymize(IpAddress::parse('::ffff:192.0.2.123')));
    }

    public function testUsesThePrefixesItIsGiven(): void
    {
        $truncation = new Truncation(16, 64);

        self::assertSame('192.0.0.0', $truncation->anonymize(IpAddress::parse('192.0.2.123')));
        self::assertSame('2001:db8:1234:5678::', $truncation->anonymize(IpAddress::parse('2001:db8:1234:5678:9abc::1')));
    }

    public function testTransformsAnAddressIntoAnAddress(): void
    {
        $truncated = new Truncation(8, 16)->transform(IpAddress::parse('2001:db8::1'));

        self::assertSame(IpFamily::V6, $truncated->family);
        self::assertSame('2001::', $truncated->toString());
    }

    public function testRefusesAnIpv4PrefixOutOfBounds(): void
    {
        $this->expectException(InvalidNetwork::class);

        new Truncation(33);
    }

    public function testRefusesAnIpv6PrefixOutOfBounds(): void
    {
        $this->expectException(InvalidNetwork::class);

        new Truncation(24, 129);
    }
}
