<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidIpAddress;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\IpFamily;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IpAddress::class)]
#[CoversClass(IpFamily::class)]
final class IpAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, IpFamily, string}>
     */
    public static function addresses(): iterable
    {
        yield 'IPv4' => ['192.0.2.1', '192.0.2.1', IpFamily::V4, 'c0000201'];
        yield 'IPv4 zero' => ['0.0.0.0', '0.0.0.0', IpFamily::V4, '00000000'];
        yield 'IPv4 with zero octets' => ['10.0.0.1', '10.0.0.1', IpFamily::V4, '0a000001'];
        yield 'IPv6 compressed' => ['2001:db8::1', '2001:db8::1', IpFamily::V6, '20010db8000000000000000000000001'];
        yield 'IPv6 uppercase, leading zeros' => ['2001:0DB8:0000::0001', '2001:db8::1', IpFamily::V6, '20010db8000000000000000000000001'];
        yield 'IPv6 trailing hextet with a leading zero' => ['2001:db8::01', '2001:db8::1', IpFamily::V6, '20010db8000000000000000000000001'];
        yield 'IPv6 unspecified' => ['::', '::', IpFamily::V6, '00000000000000000000000000000000'];
        yield 'IPv4-mapped' => ['::ffff:192.0.2.1', '192.0.2.1', IpFamily::V4, 'c0000201'];
        yield 'IPv4-mapped uppercase' => ['::FFFF:10.0.0.1', '10.0.0.1', IpFamily::V4, '0a000001'];
        yield 'IPv4-mapped in hexadecimal' => ['::ffff:c000:201', '192.0.2.1', IpFamily::V4, 'c0000201'];
        yield 'IPv4-compatible stays IPv6' => ['::192.0.2.1', '::c000:201', IpFamily::V6, '000000000000000000000000c0000201'];
        yield 'embedded IPv4 after a hextet with a leading zero' => ['2001:db8:0001::192.0.2.1', '2001:db8:1::c000:201', IpFamily::V6, '20010db80001000000000000c0000201'];
        yield 'one zero group is not compressed' => ['2001:db8:0:1:1:1:1:1', '2001:db8:0:1:1:1:1:1', IpFamily::V6, '20010db8000000010001000100010001'];
        yield 'the longest run is compressed' => ['2001:0:0:1:0:0:0:1', '2001:0:0:1::1', IpFamily::V6, '20010000000000010000000000000001'];
        yield 'the first of equal runs is compressed' => ['2001:db8:0:0:1:0:0:1', '2001:db8::1:0:0:1', IpFamily::V6, '20010db8000000000001000000000001'];
        yield 'a run at the end' => ['2001:db8:1::', '2001:db8:1::', IpFamily::V6, '20010db8000100000000000000000000'];
        yield 'a run at the start' => ['0:0:0:0:0:0:0:1', '::1', IpFamily::V6, '00000000000000000000000000000001'];
        yield 'a group of ten' => ['10:a0:100:1000::', '10:a0:100:1000::', IpFamily::V6, '001000a0010010000000000000000000'];
        yield 'longest text' => ['ffff:ffff:ffff:ffff:ffff:ffff:255.255.255.255', 'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', IpFamily::V6, str_repeat('ff', 16)];
    }

    #[DataProvider('addresses')]
    public function testParsesAnAddress(string $text, string $canonical, IpFamily $family, string $hex): void
    {
        $address = IpAddress::parse($text);

        self::assertSame($canonical, $address->toString());
        self::assertSame($canonical, (string) $address);
        self::assertSame($family, $address->family);
        self::assertSame($hex, bin2hex($address->bytes));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'three octets' => ['192.0.2'];
        yield 'octet above 255' => ['192.0.2.256'];
        yield 'leading zero in the first octet' => ['010.0.0.1'];
        yield 'leading zero in a later octet' => ['10.0.0.01'];
        yield 'leading zero in a mapped address' => ['::ffff:10.0.00.1'];
        yield 'leading zero right after the colon' => ['::ffff:01.0.0.1'];
        yield 'zone index' => ['fe80::1%eth0'];
        yield 'zone index made of digits' => ['fe80::1%1'];
        yield 'brackets' => ['[::1]'];
        yield 'leading space' => [' 192.0.2.1'];
        yield 'trailing newline' => ["192.0.2.1\n"];
        yield 'NUL byte' => ["192.0.2.1\0"];
        yield 'two double colons' => ['1::2::3'];
        yield 'hexadecimal octet' => ['0x1.0.0.1'];
        yield 'host name' => ['localhost'];
        yield 'one character too long' => ['ffff:ffff:ffff:ffff:ffff:ffff:255.255.255.2550'];
        yield 'letters only' => ['abcdef'];
    }

    #[DataProvider('invalidTexts')]
    public function testRefusesWhatIsNotAnAddress(string $text): void
    {
        self::assertNull(IpAddress::tryParse($text));

        $this->expectException(InvalidIpAddress::class);
        $this->expectExceptionMessageIsOrContains('The text is not an IPv4 or IPv6 address.');

        IpAddress::parse($text);
    }

    public function testReadsBytes(): void
    {
        self::assertSame('192.0.2.1', IpAddress::fromBytes("\xC0\x00\x02\x01")->toString());
        self::assertSame(IpFamily::V4, IpAddress::fromBytes(str_repeat("\x00", 10)."\xFF\xFF\xC0\x00\x02\x01")->family);
        self::assertSame('::ffff:0:c000:201', IpAddress::fromBytes(str_repeat("\x00", 8)."\xFF\xFF\x00\x00\xC0\x00\x02\x01")->toString());
    }

    public function testRefusesBytesOfAnotherLength(): void
    {
        $this->expectException(InvalidIpAddress::class);
        $this->expectExceptionMessageIsOrContains('An address is 4 bytes (IPv4) or 16 bytes (IPv6) long.');

        IpAddress::fromBytes("\x01\x02\x03");
    }

    public function testComparesAddresses(): void
    {
        self::assertTrue(IpAddress::parse('::ffff:192.0.2.1')->equals(IpAddress::parse('192.0.2.1')));
        self::assertFalse(IpAddress::parse('192.0.2.1')->equals(IpAddress::parse('192.0.2.2')));
    }

    public function testReadsBackItsOwnText(): void
    {
        for ($i = 0; $i < 500; ++$i) {
            $bytes = substr_replace(random_bytes(16), str_repeat("\x00", random_int(0, 8)), random_int(0, 8) * 2, 0);
            $address = IpAddress::fromBytes(substr($bytes, 0, 16));
            $text = $address->toString();

            self::assertTrue(IpAddress::parse($text)->equals($address), $text);
            self::assertSame($address->bytes, inet_pton($text), $text);
        }
    }

    public function testKnowsTheBitsOfEachFamily(): void
    {
        self::assertSame(32, IpFamily::V4->bits());
        self::assertSame(128, IpFamily::V6->bits());
    }
}
