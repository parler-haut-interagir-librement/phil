<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use Phil\AccessCodes\CodeHasher;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeHasher::class)]
final class CodeHasherTest extends TestCase
{
    private const string KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private const string OLD = 'oooooooooooooooooooooooooooooooo';

    public function testTheHashIsTheHmacOfTheCodeUnderTheActiveKey(): void
    {
        $hash = new CodeHasher(self::KEY, self::OLD)->hash('0000000000000');

        self::assertSame('$hmac-sha256$v=1$c61abf4cfb959a467cf6f752605c534aa947eedb2d0283205931348797083594', $hash);
        self::assertSame(CodeHasher::LENGTH, \strlen($hash));
    }

    public function testTheCandidatesAreTheHashUnderEveryKeyActiveFirst(): void
    {
        $candidates = new CodeHasher(self::KEY, self::OLD)->candidates('0000000000000');

        self::assertSame([new CodeHasher(self::KEY)->hash('0000000000000'), new CodeHasher(self::OLD)->hash('0000000000000')], $candidates);
    }

    public function testAHashUnderAnyKeyMatches(): void
    {
        $hasher = new CodeHasher(self::KEY, self::OLD);

        self::assertTrue($hasher->matches(new CodeHasher(self::OLD)->hash('ABC'), 'ABC'));
        self::assertTrue($hasher->matches(new CodeHasher(self::KEY)->hash('ABC'), 'ABC'));
        self::assertFalse($hasher->matches(new CodeHasher(self::KEY)->hash('ABD'), 'ABC'));
        self::assertFalse(new CodeHasher(self::KEY)->matches(new CodeHasher(self::OLD)->hash('ABC'), 'ABC'));
    }

    public function testDroppingAKeyLosesItsCodes(): void
    {
        self::assertNotContains(new CodeHasher(self::OLD)->hash('ABC'), new CodeHasher(self::KEY)->candidates('ABC'));
    }

    public function testAKeyIsThirtyTwoBytesAtTheLeast(): void
    {
        self::assertCount(1, new CodeHasher(str_repeat('a', 32))->candidates('A'));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A key of the code hashes is at least 32 bytes: read it from the secrets vault.');

        new CodeHasher(self::KEY, str_repeat('a', 31));
    }

    public function testEightKeysAtTheMost(): void
    {
        $keys = array_map(static fn (int $i): string => str_repeat((string) $i, 32), range(1, 8));

        self::assertCount(8, new CodeHasher(...$keys)->candidates('A'));

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('At most 8 keys hash the codes: drop the oldest.');

        new CodeHasher(str_repeat('9', 32), ...$keys);
    }

    public function testAKeyGivenTwiceIsRefused(): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A key of the code hashes is given twice.');

        new CodeHasher(self::KEY, self::OLD, self::KEY);
    }

    public function testNamedPreviousKeysAreKept(): void
    {
        self::assertSame(
            [new CodeHasher(self::KEY)->hash('A'), new CodeHasher(self::OLD)->hash('A')],
            new CodeHasher(self::KEY, ...['old' => self::OLD])->candidates('A'),
        );
    }
}
