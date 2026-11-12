<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\Internal\PrefixMask;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\IpNetwork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IpNetwork::class)]
#[CoversClass(PrefixMask::class)]
final class IpNetworkTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function containingNetworks(): iterable
    {
        yield 'IPv4 /24' => ['192.0.2.123', 24, '192.0.2.0/24'];
        yield 'IPv4 /0' => ['192.0.2.123', 0, '0.0.0.0/0'];
        yield 'IPv4 /32' => ['192.0.2.123', 32, '192.0.2.123/32'];
        yield 'IPv4 /1' => ['192.0.2.123', 1, '128.0.0.0/1'];
        yield 'IPv4 /7' => ['255.255.255.255', 7, '254.0.0.0/7'];
        yield 'IPv4 /9' => ['255.255.255.255', 9, '255.128.0.0/9'];
        yield 'IPv4 /31' => ['255.255.255.255', 31, '255.255.255.254/31'];
        yield 'IPv6 /48' => ['2001:db8:1234:5678::1', 48, '2001:db8:1234::/48'];
        yield 'IPv6 /0' => ['2001:db8::1', 0, '::/0'];
        yield 'IPv6 /128' => ['2001:db8::1', 128, '2001:db8::1/128'];
        yield 'IPv6 /127' => ['ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 127, 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:fffe/127'];
        yield 'IPv6 /57' => ['ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 57, 'ffff:ffff:ffff:ff80::/57'];
    }

    #[DataProvider('containingNetworks')]
    public function testFindsTheNetworkOfAnAddress(string $address, int $length, string $expected): void
    {
        $network = IpNetwork::containing(IpAddress::parse($address), $length);

        self::assertSame($expected, $network->toString());
        self::assertSame($expected, (string) $network);
        self::assertSame($length, $network->prefixLength);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidLengths(): iterable
    {
        yield 'negative IPv4' => ['192.0.2.1', -1, 'An ipv4 prefix length is from 0 to 32; -1 is not.'];
        yield 'too long IPv4' => ['192.0.2.1', 33, 'An ipv4 prefix length is from 0 to 32; 33 is not.'];
        yield 'negative IPv6' => ['2001:db8::1', -1, 'An ipv6 prefix length is from 0 to 128; -1 is not.'];
        yield 'too long IPv6' => ['2001:db8::1', 129, 'An ipv6 prefix length is from 0 to 128; 129 is not.'];
    }

    #[DataProvider('invalidLengths')]
    public function testRefusesALengthOutsideTheFamily(string $address, int $length, string $message): void
    {
        $this->expectException(InvalidNetwork::class);
        $this->expectExceptionMessageIsOrContains($message);

        IpNetwork::containing(IpAddress::parse($address), $length);
    }

    public function testParsesANetwork(): void
    {
        self::assertSame('192.0.2.0/24', IpNetwork::parse('192.0.2.0/24')->toString());
        self::assertSame('2001:db8::/32', IpNetwork::parse('2001:DB8::/32')->toString());
        self::assertSame('0.0.0.0/0', IpNetwork::parse('0.0.0.0/0')->toString());
        self::assertSame('::/0', IpNetwork::parse('::/0')->toString());
        self::assertSame('2001:db8::1/128', IpNetwork::parse('2001:db8::1/128')->toString());
        self::assertSame('192.0.2.0/24', IpNetwork::parse('::ffff:192.0.2.0/24')->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidNetworks(): iterable
    {
        $notation = 'A network is written "<address>/<prefix length>".';

        yield 'no length' => ['192.0.2.0', $notation];
        yield 'empty length' => ['192.0.2.0/', $notation];
        yield 'two lengths' => ['192.0.2.0/24/24', $notation];
        yield 'leading zero in the length' => ['192.0.2.0/024', $notation];
        yield 'signed length' => ['192.0.2.0/+24', $notation];
        yield 'four digits' => ['::/1000', $notation];
        yield 'trailing newline' => ["192.0.2.0/24\n", $notation];
        yield 'text before the length' => ['192.0.2.0/a24', $notation];
        yield 'not an address' => ['example/24', 'The address of a network is an IPv4 or IPv6 address.'];
        yield 'host bits set' => ['192.0.2.1/24', 'The address of a network has its host bits clear.'];
        yield 'IPv6 host bits set' => ['2001:db8::1/64', 'The address of a network has its host bits clear.'];
        yield 'length too long' => ['192.0.2.0/33', 'An ipv4 prefix length is from 0 to 32; 33 is not.'];
    }

    #[DataProvider('invalidNetworks')]
    public function testRefusesWhatIsNotANetwork(string $cidr, string $message): void
    {
        $this->expectException(InvalidNetwork::class);
        $this->expectExceptionMessageIsOrContains($message);

        IpNetwork::parse($cidr);
    }

    public function testTellsWhetherItHoldsAnAddress(): void
    {
        $network = IpNetwork::parse('192.0.2.0/24');

        self::assertTrue($network->contains(IpAddress::parse('192.0.2.0')));
        self::assertTrue($network->contains(IpAddress::parse('192.0.2.255')));
        self::assertTrue($network->contains(IpAddress::parse('::ffff:192.0.2.9')));
        self::assertFalse($network->contains(IpAddress::parse('192.0.3.0')));
        self::assertFalse($network->contains(IpAddress::parse('c000:200::')));
        self::assertFalse(IpNetwork::parse('::/0')->contains(IpAddress::parse('192.0.2.1')));
        self::assertTrue(IpNetwork::parse('::/0')->contains(IpAddress::parse('2001:db8::1')));
    }

    public function testComparesNetworks(): void
    {
        self::assertTrue(IpNetwork::parse('192.0.2.0/24')->equals(IpNetwork::parse('192.0.2.0/24')));
        self::assertFalse(IpNetwork::parse('192.0.2.0/24')->equals(IpNetwork::parse('192.0.2.0/25')));
        self::assertFalse(IpNetwork::parse('192.0.2.0/24')->equals(IpNetwork::parse('192.0.3.0/24')));
    }
}
