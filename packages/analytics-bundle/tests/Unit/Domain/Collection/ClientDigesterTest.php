<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Collection;

use Phil\AnalyticsBundle\Domain\Collection\ClientDigester;
use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\IpAnonymizer\FixedSalt;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\Salt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientDigester::class)]
final class ClientDigesterTest extends TestCase
{
    private ClientDigester $digester;

    private Salt $salt;

    protected function setUp(): void
    {
        $this->salt = Salt::of('k1', str_repeat("\x07", 32));
        $this->digester = new ClientDigester(new FixedSalt($this->salt));
    }

    public function testTheDigestIsTheKeyedHashOfTheAddress(): void
    {
        $expected = sodium_bin2hex(sodium_crypto_generichash(IpAddress::parse('192.0.2.1')->bytes, $this->salt->bytes, 16));

        self::assertSame('k1:'.$expected, $this->digester->digestOf('192.0.2.1')->toString());
    }

    public function testEveryAddressOfAnIpv6Slash64IsOneDigest(): void
    {
        $digest = $this->digester->digestOf('2001:db8:1:2::1');

        self::assertTrue($digest->equals($this->digester->digestOf('2001:db8:1:2:ffff:ffff:ffff:ffff')));
        self::assertFalse($digest->equals($this->digester->digestOf('2001:db8:1:3::1')));
    }

    public function testTwoIpv4AddressesDiffer(): void
    {
        self::assertFalse($this->digester->digestOf('192.0.2.1')->equals($this->digester->digestOf('192.0.2.2')));
    }

    public function testAnIpv4MappedAddressIsTheIpv4Address(): void
    {
        self::assertTrue($this->digester->digestOf('::ffff:192.0.2.1')->equals($this->digester->digestOf('192.0.2.1')));
    }

    public function testAnotherSaltIsAnotherDigest(): void
    {
        $other = new ClientDigester(new FixedSalt(Salt::of('k2', str_repeat("\x08", 32))));

        self::assertFalse($this->digester->digestOf('192.0.2.1')->equals($other->digestOf('192.0.2.1')));
    }

    public function testTheDigestDoesNotContainTheAddress(): void
    {
        self::assertInstanceOf(ClientDigest::class, $digest = $this->digester->digestOf('192.0.2.1'));
        self::assertStringNotContainsString('192.0.2.1', $digest->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAnAddress(): iterable
    {
        yield 'empty' => [''];
        yield 'a host name' => ['example.com'];
        yield 'five octets' => ['1.2.3.4.5'];
    }

    #[DataProvider('notAnAddress')]
    public function testWhatIsNotAnAddressIsRefused(string $ip): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The client address is not an IPv4 or IPv6 address.');

        $this->digester->digestOf($ip);
    }
}
