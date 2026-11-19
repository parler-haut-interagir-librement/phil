<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit;

use Phil\Avatar\Exception\InvalidAvatar;
use Phil\Avatar\Random;
use Phil\Avatar\Seed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Seed::class)]
#[CoversClass(Random::class)]
final class SeedAndRandomTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidSeeds(): iterable
    {
        $shape = 'A seed is 1 to 255 visible ASCII characters.';

        yield 'empty' => ['', $shape];
        yield 'a space' => ['user 1', $shape];
        yield 'a new line after' => ["user-1\n", $shape];
        yield 'an accent' => ['usér', $shape];
        yield 'too long' => [str_repeat('u', 256), $shape];
        yield 'an email address' => ['ada@example.com', 'A seed is an opaque identifier, never an email address.'];
    }

    #[DataProvider('invalidSeeds')]
    public function testASeedIsOpaque(string $seed, string $message): void
    {
        $this->expectExceptionObject(InvalidAvatar::because($message));

        new Seed($seed);
    }

    public function testTheBoundsOfASeed(): void
    {
        self::assertSame(255, \strlen(new Seed(str_repeat('u', 255))->value));
        self::assertSame('!~', new Seed('!~')->value);
    }

    public function testTheSameSeedDrawsTheSameNumbers(): void
    {
        $one = Random::of(new Seed('user-1'), 'shapes');
        $two = Random::of(new Seed('user-1'), 'shapes');
        $draws = [];

        for ($i = 0; $i < 20; ++$i) {
            $draw = $one->int(0, 1000);
            self::assertSame($draw, $two->int(0, 1000));
            $draws[] = $draw;
        }

        // Twenty draws read past the eight words of the first block.
        self::assertGreaterThan(10, \count(array_unique($draws)));
    }

    public function testTheStreamIsFixed(): void
    {
        $random = new Random('fixed');
        $words = $this->words(unpack('N8', hash('sha256', "phil/avatar v1\0fixed\0".'0', true)));

        self::assertSame($words[1], $random->int(0, 0xFFFFFFFF));
        self::assertSame($words[2], $random->int(0, 0xFFFFFFFF));
        self::assertSame(7 + $words[3] % 10, $random->int(7, 16));
    }

    public function testTheSecondBlockFollowsTheFirst(): void
    {
        $random = new Random('fixed');

        for ($i = 0; $i < 8; ++$i) {
            $random->int(0, 0xFFFFFFFF);
        }

        $words = $this->words(unpack('N', hash('sha256', "phil/avatar v1\0fixed\0".'1', true)));
        self::assertSame($words[1], $random->int(0, 0xFFFFFFFF));
    }

    public function testAnotherStyleOrSeedDrawsOtherNumbers(): void
    {
        $draw = static fn (Random $random): array => [$random->int(0, 0xFFFFFFFF), $random->int(0, 0xFFFFFFFF)];

        self::assertNotSame($draw(Random::of(new Seed('user-1'), 'shapes')), $draw(Random::of(new Seed('user-1'), 'pixels')));
        self::assertNotSame($draw(Random::of(new Seed('user-1'), 'shapes')), $draw(Random::of(new Seed('user-2'), 'shapes')));
        self::assertSame($draw(new Random("shapes\0user-1")), $draw(Random::of(new Seed('user-1'), 'shapes')));
    }

    public function testDrawsStayInTheirRangeAndCoverIt(): void
    {
        $random = new Random('range');
        $seen = [];

        for ($i = 0; $i < 400; ++$i) {
            $draw = $random->int(-2, 2);
            self::assertGreaterThanOrEqual(-2, $draw);
            self::assertLessThanOrEqual(2, $draw);
            $seen[$draw] = true;
        }

        ksort($seen);
        self::assertSame([-2, -1, 0, 1, 2], array_keys($seen));
        self::assertSame(5, $random->int(5, 5));
    }

    public function testADrawPastTheLargestMultipleIsDrawnAgain(): void
    {
        // A range of 3 × 2^30 numbers leaves out the draws of 3 × 2^30 and above: about one in four.
        $random = new Random('rejection');
        $range = 3 * 2 ** 30;
        $plain = new Random('rejection');
        $raw = [];

        for ($i = 0; $i < 64; ++$i) {
            $raw[] = $plain->int(0, 0xFFFFFFFF);
        }

        $accepted = array_values(array_filter($raw, static fn (int $draw): bool => $draw < $range));

        for ($i = 0; $i < 10; ++$i) {
            self::assertSame($accepted[$i], $random->int(0, $range - 1));
        }
    }

    public function testPicksAndChances(): void
    {
        $random = new Random('pick');
        $picked = [];
        $yes = 0;

        for ($i = 0; $i < 300; ++$i) {
            $picked[$random->pick(['a', 'b', 'c'])] = true;
            $yes += $random->chance(30) ? 1 : 0;
        }

        ksort($picked);
        self::assertSame(['a', 'b', 'c'], array_keys($picked));
        self::assertGreaterThan(60, $yes);
        self::assertLessThan(120, $yes);
        self::assertTrue(new Random('x')->chance(100));
        self::assertFalse(new Random('x')->chance(0));
        self::assertSame('only', new Random('x')->pick(['only']));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'backwards' => [2, 1];
        yield 'wider than 2^32' => [0, 0x100000000];
    }

    #[DataProvider('invalidRanges')]
    public function testARangeOutOfBoundsIsRefused(int $min, int $max): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('A random number is drawn from a range of 1 to 2^32 numbers.'));

        new Random('x')->int($min, $max);
    }

    /**
     * @return array<int, int>
     */
    private function words(mixed $unpacked): array
    {
        self::assertIsArray($unpacked);
        $words = [];

        foreach ($unpacked as $index => $word) {
            self::assertIsInt($index);
            self::assertIsInt($word);
            $words[$index] = $word;
        }

        return $words;
    }
}
