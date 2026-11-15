<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptKey;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentAttemptKey::class)]
final class PaymentAttemptKeyTest extends TestCase
{
    public function testAnOrderKeyAndAnIpKeyNeverCollide(): void
    {
        self::assertSame('order:42', PaymentAttemptKey::forOrder('42')->value);
        self::assertSame('ip:203.0.113.7', PaymentAttemptKey::forClientIp('203.0.113.7')->value);
        self::assertFalse(PaymentAttemptKey::forOrder('203.0.113.7')->equals(PaymentAttemptKey::forClientIp('203.0.113.7')));
    }

    /**
     * Two spellings of one IPv6 address would otherwise be two states, and twice the budget.
     */
    public function testTwoSpellingsOfOneIpv6AddressShareOneKey(): void
    {
        $long = PaymentAttemptKey::forClientIp('2001:0DB8:0000:0000:0000:0000:0000:0001');
        $short = PaymentAttemptKey::forClientIp('2001:db8::1');

        self::assertTrue($long->equals($short));
        self::assertSame('ip:2001:db8::/64', $short->value);
    }

    /**
     * An IPv6 client holds at least a /64: a new address on each attempt must not buy a new budget.
     */
    public function testTwoAddressesOfOneIpv6NetworkShareOneKey(): void
    {
        $first = PaymentAttemptKey::forClientIp('2001:db8:0:1::1');
        $second = PaymentAttemptKey::forClientIp('2001:db8:0:1:ffff:ffff:ffff:ffff');

        self::assertTrue($first->equals($second));
        self::assertSame('ip:2001:db8:0:1::/64', $first->value);
        self::assertFalse($first->equals(PaymentAttemptKey::forClientIp('2001:db8:0:2::1')));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function prefixLengths(): iterable
    {
        yield 'a /48' => [48, 'ip:2001:db8:abcd::/48'];
        yield 'a prefix inside a byte' => [52, 'ip:2001:db8:abcd:1000::/52'];
        yield 'one bit' => [1, 'ip:::/1'];
        yield 'the whole address' => [128, 'ip:2001:db8:abcd:1234::1/128'];
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function prefixesOfAFullAddress(): iterable
    {
        yield '/1' => [1, 'ip:8000::/1'];
        yield '/7' => [7, 'ip:fe00::/7'];
        yield '/8' => [8, 'ip:ff00::/8'];
        yield '/9' => [9, 'ip:ff80::/9'];
        yield '/15' => [15, 'ip:fffe::/15'];
        yield '/17' => [17, 'ip:ffff:8000::/17'];
        yield '/63' => [63, 'ip:ffff:ffff:ffff:fffe::/63'];
        yield '/64' => [64, 'ip:ffff:ffff:ffff:ffff::/64'];
        yield '/127' => [127, 'ip:ffff:ffff:ffff:ffff:ffff:ffff:ffff:fffe/127'];
        yield '/128' => [128, 'ip:ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff/128'];
    }

    /**
     * Every bit set: each bit past the prefix, and only those, must come out cleared.
     */
    #[DataProvider('prefixesOfAFullAddress')]
    public function testEveryBitPastThePrefixIsCleared(int $length, string $expected): void
    {
        self::assertSame($expected, PaymentAttemptKey::forClientIp('ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', $length)->value);
    }

    #[DataProvider('prefixLengths')]
    public function testTheIpv6PrefixIsTheCallersChoice(int $length, string $expected): void
    {
        self::assertSame($expected, PaymentAttemptKey::forClientIp('2001:db8:abcd:1234::1', $length)->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badPrefixLengths(): iterable
    {
        yield 'zero' => [0];
        yield 'beyond the address' => [129];
    }

    #[DataProvider('badPrefixLengths')]
    public function testAnImpossiblePrefixLengthIsRefused(int $length): void
    {
        $this->expectException(InvalidValue::class);

        PaymentAttemptKey::forClientIp('2001:db8::1', $length);
    }

    public function testAnIpv4KeyIgnoresThePrefixLength(): void
    {
        self::assertSame('ip:203.0.113.7', PaymentAttemptKey::forClientIp('203.0.113.7', 8)->value);
    }

    /**
     * One IPv4 client reaching a dual-stack listener arrives in its mapped form: still one state.
     */
    public function testAnIpv4MappedAddressIsItsIpv4Address(): void
    {
        $mapped = PaymentAttemptKey::forClientIp('::ffff:203.0.113.7');

        self::assertTrue($mapped->equals(PaymentAttemptKey::forClientIp('203.0.113.7')));
        self::assertSame('ip:203.0.113.7', $mapped->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIps(): iterable
    {
        yield 'empty' => [''];
        yield 'hostname' => ['example.com'];
        yield 'out of range' => ['256.0.0.1'];
        yield 'with a port' => ['203.0.113.7:443'];
    }

    #[DataProvider('notIps')]
    public function testSomethingThatIsNotAnIpIsRefused(string $ip): void
    {
        $this->expectException(InvalidValue::class);

        PaymentAttemptKey::forClientIp($ip);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badOrderIds(): iterable
    {
        yield 'empty' => [''];
        yield 'surrounding whitespace' => [' 42 '];
        yield 'a line feed' => ["4\n2"];
        yield 'a NUL byte' => ["4\x002"];
        yield 'DEL' => ["4\x7F2"];
        yield 'too long' => [str_repeat('a', 256)];
    }

    public function testTheLongestOrderIdentifierIsAccepted(): void
    {
        self::assertSame('order:'.str_repeat('a', 255), PaymentAttemptKey::forOrder(str_repeat('a', 255))->value);
    }

    #[DataProvider('badOrderIds')]
    public function testAMalformedOrderIdentifierIsRefused(string $orderId): void
    {
        $this->expectException(InvalidValue::class);

        PaymentAttemptKey::forOrder($orderId);
    }
}
