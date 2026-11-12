<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidSalt;
use Phil\IpAnonymizer\FixedSalt;
use Phil\IpAnonymizer\Salt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Salt::class)]
#[CoversClass(FixedSalt::class)]
final class SaltTest extends TestCase
{
    public function testKeepsItsIdentifierAndBytes(): void
    {
        $salt = Salt::of('2026-11.a_b', str_repeat('k', 16));

        self::assertSame('2026-11.a_b', $salt->id);
        self::assertSame(str_repeat('k', 16), $salt->bytes);
        self::assertSame(64, \strlen(Salt::of('x', str_repeat('k', 64))->bytes));
        self::assertSame(32, \strlen(Salt::of(str_repeat('x', 32), str_repeat('k', 32))->bytes));
    }

    public function testDrawsRandomBytes(): void
    {
        $salt = Salt::random('daily');

        self::assertSame('daily', $salt->id);
        self::assertSame(32, \strlen($salt->bytes));
        self::assertNotSame($salt->bytes, Salt::random('daily')->bytes);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('x', 33)];
        yield 'colon' => ['a:b'];
        yield 'space' => ['a b'];
        yield 'trailing newline' => ["a\n"];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testRefusesAnInvalidIdentifier(string $id): void
    {
        $this->expectException(InvalidSalt::class);
        $this->expectExceptionMessageIsOrContains('A salt identifier is 1 to 32 characters among letters, digits, ".", "-" and "_".');

        Salt::of($id, str_repeat('k', 32));
    }

    public function testRefusesTooFewBytes(): void
    {
        $this->expectException(InvalidSalt::class);
        $this->expectExceptionMessageIsOrContains('A salt is 16 to 64 bytes long; this one is 15.');

        Salt::of('a', str_repeat('k', 15));
    }

    public function testRefusesTooManyBytes(): void
    {
        $this->expectException(InvalidSalt::class);
        $this->expectExceptionMessageIsOrContains('A salt is 16 to 64 bytes long; this one is 65.');

        Salt::of('a', str_repeat('k', 65));
    }

    public function testHidesItsBytesFromDumps(): void
    {
        self::assertSame(['id' => 'a', 'bytes' => '[redacted]'], Salt::of('a', str_repeat('k', 32))->__debugInfo());
    }

    public function testAFixedProviderAlwaysGivesItsSalt(): void
    {
        $salt = Salt::of('a', str_repeat('k', 32));

        self::assertSame($salt, new FixedSalt($salt)->current());
    }
}
