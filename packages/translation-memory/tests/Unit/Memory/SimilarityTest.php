<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Memory;

use Phil\TranslationMemory\Memory\Similarity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

#[CoversClass(Similarity::class)]
final class SimilarityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function distances(): iterable
    {
        yield 'equal' => ['kitten', 'kitten', 0];
        yield 'classic' => ['kitten', 'sitting', 3];
        yield 'empty left' => ['', 'abc', 3];
        yield 'empty right' => ['abc', '', 3];
        yield 'both empty' => ['', '', 0];
        yield 'insertion' => ['abc', 'abxc', 1];
        yield 'deletion' => ['abxc', 'abc', 1];
        yield 'substitution' => ['abc', 'axc', 1];
        yield 'transposition' => ['ab', 'ba', 2];
        yield 'code points' => ['été', 'ete', 2];
        yield 'all different' => ['abc', 'xyz', 3];
        yield 'shifted' => ['abcdef', 'bcdefa', 2];
    }

    #[DataProvider('distances')]
    public function testCountsTheEditsBetweenTwoTexts(string $a, string $b, int $distance): void
    {
        self::assertSame($distance, Similarity::distance(mb_str_split($a), mb_str_split($b)));
        self::assertSame($distance, Similarity::distance(mb_str_split($b), mb_str_split($a)));

        for ($bound = 0; $bound <= $distance + 2; ++$bound) {
            self::assertSame($bound >= $distance ? $distance : null, Similarity::distance(mb_str_split($a), mb_str_split($b), $bound), 'bound '.$bound);
        }
    }

    public function testGivesUpPastTheBoundWithinTheBand(): void
    {
        self::assertNull(Similarity::distance(mb_str_split('aaaaaaaaaa'), mb_str_split('bbbbbbbbbb'), 3));
        self::assertSame(1, Similarity::distance(mb_str_split('abcdefghij'), mb_str_split('abcdefghiX'), 1));
        self::assertNull(Similarity::distance(mb_str_split('abc'), mb_str_split('abcdef'), 2));
    }

    public function testCountsEachCharacterOnItsOwnPastTwoHundredAndFiftySixOfThem(): void
    {
        $symbols = array_map(mb_chr(...), range(0x100, 0x200));
        $swapped = [$symbols[256], ...\array_slice($symbols, 1, 255), $symbols[0]];
        $substituted = ['a', ...\array_slice($symbols, 1)];

        self::assertSame(2, Similarity::distance($symbols, $swapped));
        self::assertNull(Similarity::distance($symbols, $swapped, 1));
        self::assertSame(1, Similarity::distance($symbols, $substituted, 1));
        self::assertSame(1, Similarity::distance($substituted, $symbols, 1));
        self::assertSame(1, Similarity::distance(['a', ...array_fill(0, 255, 'x')], ['b', ...array_fill(0, 255, 'x')]));
    }

    public function testAnEqualListOfManyCharactersIsAtDistanceZeroWithinANullBound(): void
    {
        $symbols = array_map(mb_chr(...), range(0x100, 0x200));

        self::assertSame(0, Similarity::distance($symbols, $symbols, 0));
        self::assertSame(1, Similarity::distance($symbols, \array_slice($symbols, 0, 256), 1));
        self::assertSame(2, Similarity::distance(\array_slice($symbols, 0, 255), $symbols, 2));
    }

    /**
     * Texts of a few letters, and texts of hundreds of different characters, give the distance of the whole table.
     */
    public function testGivesTheDistanceOfTheWholeTable(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(7));

        foreach ([4 => 12, 600 => 400] as $alphabet => $length) {
            for ($case = 0; $case < 30; ++$case) {
                $a = $this->text($randomizer, $alphabet, $randomizer->getInt(0, $length));
                $b = $this->edited($randomizer, $a, $alphabet, $randomizer->getInt(0, 1 + intdiv($length, 4)));
                $expected = $this->reference($a, $b);

                self::assertSame($expected, Similarity::distance($a, $b), 'case '.$case);
                self::assertSame($expected, Similarity::distance($a, $b, $expected), 'case '.$case);
                self::assertSame($expected, Similarity::distance($a, $b, $expected + 1), 'case '.$case);
                self::assertNull(Similarity::distance($a, $b, $expected - 1), 'case '.$case);
            }
        }
    }

    public function testScoresTwoTextsFromZeroToOne(): void
    {
        self::assertSame(1.0, Similarity::score('', ''));
        self::assertSame(1.0, Similarity::score('abc', 'abc'));
        self::assertEqualsWithDelta(1 / 3, Similarity::score('été', 'ete'), 1e-9);
        self::assertEqualsWithDelta(1 / 3, Similarity::score('ete', 'été'), 1e-9);
        self::assertEqualsWithDelta(4 / 7, Similarity::score('kitten', 'sitting'), 1e-9);
        self::assertSame(0.0, Similarity::score('abc', 'xyz'));
        self::assertSame(0.5, Similarity::score('abcd', 'abxy', 0.5));
        self::assertSame(0.0, Similarity::score('abcd', 'axyz', 0.5));
        self::assertSame(0.0, Similarity::score('kitten', 'sitting', 0.6));
        self::assertEqualsWithDelta(4 / 7, Similarity::score('kitten', 'sitting', 0.57), 1e-9);
    }

    public function testCountsTheTrigramsOfAText(): void
    {
        self::assertSame([' ab' => 1, 'abc' => 1, 'bc ' => 1], Similarity::trigrams('ABC'));
        self::assertSame([' aa' => 1, 'aaa' => 2, 'aa ' => 1], Similarity::trigrams('aaaa'));
        self::assertSame([' é ' => 1], Similarity::trigrams('É'));
        self::assertSame([], Similarity::trigrams(''));
    }

    public function testComparesTrigrams(): void
    {
        self::assertSame(1.0, Similarity::dice([], []));
        self::assertSame(1.0, Similarity::dice(['abc' => 2], ['abc' => 2]));
        self::assertSame(0.0, Similarity::dice(['abc' => 1], ['xyz' => 1]));
        self::assertEqualsWithDelta(2 / 3, Similarity::dice(['abc' => 2], ['abc' => 1]), 1e-9);
        self::assertEqualsWithDelta(0.5, Similarity::dice(['abc' => 1, 'bcd' => 1], ['abc' => 1, 'xyz' => 1]), 1e-9);
    }

    /**
     * @return list<string>
     */
    private function text(Randomizer $randomizer, int $alphabet, int $length): array
    {
        $text = [];

        for ($index = 0; $index < $length; ++$index) {
            $text[] = mb_chr(0x100 + $randomizer->getInt(0, $alphabet - 1));
        }

        return $text;
    }

    /**
     * @param list<string> $text
     *
     * @return list<string>
     */
    private function edited(Randomizer $randomizer, array $text, int $alphabet, int $edits): array
    {
        for ($edit = 0; $edit < $edits; ++$edit) {
            $at = $randomizer->getInt(0, \count($text));
            $character = $this->text($randomizer, $alphabet, 1);
            $text = match ($randomizer->getInt(0, 2)) {
                0 => [...\array_slice($text, 0, $at), ...$character, ...\array_slice($text, $at)],
                1 => [...\array_slice($text, 0, $at), ...\array_slice($text, $at + 1)],
                default => [...\array_slice($text, 0, $at), ...$character, ...\array_slice($text, $at + 1)],
            };
        }

        return $text;
    }

    /**
     * The distance by the whole table, the plain way.
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private function reference(array $a, array $b): int
    {
        $previous = range(0, \count($b));

        foreach ($a as $row => $left) {
            $current = [$row + 1];

            foreach ($b as $column => $right) {
                $current[] = min($previous[$column] + ($left === $right ? 0 : 1), $previous[$column + 1] + 1, $current[$column] + 1);
            }

            $previous = $current;
        }

        return $previous[\count($b)];
    }
}
