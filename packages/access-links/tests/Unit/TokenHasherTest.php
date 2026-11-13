<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\TokenHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenHasher::class)]
final class TokenHasherTest extends TestCase
{
    private const string KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private const string OLD = 'oooooooooooooooooooooooooooooooo';

    public function testTheHashIsTheHmacOfTheTokenUnderTheActiveKey(): void
    {
        $hash = new TokenHasher(self::KEY, self::OLD)->hash('tok');

        self::assertSame('$hmac-sha256$v=1$9e0cc32c84babd022c133de4ad5b5f83e14ee60eefca6142ab7f92fc90a846cf', $hash);
        self::assertSame(TokenHasher::LENGTH, \strlen($hash));
    }

    public function testTheCandidatesAreTheHashUnderEveryKeyActiveFirst(): void
    {
        self::assertSame(
            [new TokenHasher(self::KEY)->hash('tok'), new TokenHasher(self::OLD)->hash('tok')],
            new TokenHasher(self::KEY, ...['old' => self::OLD])->candidates('tok'),
        );
    }

    public function testAHashUnderAnyKeyMatches(): void
    {
        $hasher = new TokenHasher(self::KEY, self::OLD);

        self::assertTrue($hasher->matches(new TokenHasher(self::OLD)->hash('tok'), 'tok'));
        self::assertTrue($hasher->matches(new TokenHasher(self::KEY)->hash('tok'), 'tok'));
        self::assertFalse($hasher->matches(new TokenHasher(self::KEY)->hash('tol'), 'tok'));
        self::assertFalse(new TokenHasher(self::KEY)->matches(new TokenHasher(self::OLD)->hash('tok'), 'tok'));
    }

    public function testAKeyIsThirtyTwoBytesAtTheLeast(): void
    {
        self::assertCount(1, new TokenHasher(str_repeat('a', 32))->candidates('t'));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A key of the link tokens is at least 32 bytes: read it from the secrets vault.');

        new TokenHasher(self::KEY, str_repeat('a', 31));
    }

    public function testEightKeysAtTheMost(): void
    {
        $keys = array_map(static fn (int $i): string => str_repeat((string) $i, 32), range(1, 8));

        self::assertCount(8, new TokenHasher(...$keys)->candidates('t'));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('At most 8 keys hash the link tokens: drop the oldest.');

        new TokenHasher(str_repeat('9', 32), ...$keys);
    }

    public function testAKeyGivenTwiceIsRefused(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A key of the link tokens is given twice.');

        new TokenHasher(self::KEY, self::OLD, self::KEY);
    }
}
