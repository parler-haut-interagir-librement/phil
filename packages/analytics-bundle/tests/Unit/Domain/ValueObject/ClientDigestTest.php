<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientDigest::class)]
final class ClientDigestTest extends TestCase
{
    private const string VALID = '86400-20400:0123456789abcdef0123456789abcdef';

    public function testADigestReadsBackAsWritten(): void
    {
        $digest = ClientDigest::fromString(self::VALID);

        self::assertSame(self::VALID, $digest->value);
        self::assertSame(self::VALID, $digest->toString());
        self::assertSame(self::VALID, (string) $digest);
        self::assertTrue($digest->equals(ClientDigest::fromString(self::VALID)));
        self::assertFalse($digest->equals(ClientDigest::fromString('other:0123456789abcdef0123456789abcdef')));
    }

    public function testTheLongestDigestAndSaltAreAccepted(): void
    {
        $value = str_repeat('s', 32).':'.str_repeat('a', 128);

        self::assertSame($value, ClientDigest::fromString($value)->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notADigest(): iterable
    {
        yield 'an IPv4 address' => ['192.0.2.1'];
        yield 'an IPv6 address' => ['2001:db8::1'];
        yield 'no salt' => [':0123456789abcdef0123456789abcdef'];
        yield 'a digest too short' => ['s:0123456789abcdef0123456789abcde'];
        yield 'a digest too long' => ['s:'.str_repeat('a', 129)];
        yield 'a salt too long' => [str_repeat('s', 33).':0123456789abcdef0123456789abcdef'];
        yield 'upper case' => ['s:0123456789ABCDEF0123456789ABCDEF'];
        yield 'a trailing newline' => [self::VALID."\n"];
        yield 'text before' => ['x y:0123456789abcdef0123456789abcdef'];
        yield 'text after' => [self::VALID.' x'];
    }

    #[DataProvider('notADigest')]
    public function testWhatIsNotADigestIsRefused(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A client digest is written "<salt id>:<hexadecimal digest>".');

        ClientDigest::fromString($value);
    }
}
