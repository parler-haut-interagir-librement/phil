<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Internal\Search;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Search::class)]
final class SearchTest extends TestCase
{
    public function testAnEmptyPassword(): void
    {
        self::assertSame([1.0, []], Search::cheapest([], []));
    }

    public function testCharactersGuessedOneByOne(): void
    {
        [$guesses, $sequence] = Search::cheapest(['a'], []);
        self::assertSame(12.0, $guesses);
        self::assertSame([[Pattern::Bruteforce, 0, 0, 'a', 11.0]], $this->describe($sequence));

        [$guesses, $sequence] = Search::cheapest(mb_str_split('abc'), []);
        self::assertSame(1001.0, $guesses);
        self::assertSame([[Pattern::Bruteforce, 0, 2, 'abc', 1000.0]], $this->describe($sequence));
    }

    public function testAPatternThatCoversEverything(): void
    {
        [$guesses, $sequence] = Search::cheapest(mb_str_split('abcd'), [$this->match(0, 3, 'abcd', 5.0)]);

        self::assertSame(6.0, $guesses);
        self::assertSame([[Pattern::Dictionary, 0, 3, 'abcd', 5.0]], $this->describe($sequence));
    }

    public function testTwoPatternsCostTheirProductTimesTwoPlusThePenalty(): void
    {
        $characters = mb_str_split('abcdefghij');
        [$guesses, $sequence] = Search::cheapest($characters, [$this->match(0, 4, 'abcde', 100.0), $this->match(5, 9, 'fghij', 200.0)]);

        self::assertSame(2 * 100.0 * 200.0 + 10000.0, $guesses);
        self::assertSame([[Pattern::Dictionary, 0, 4, 'abcde', 100.0], [Pattern::Dictionary, 5, 9, 'fghij', 200.0]], $this->describe($sequence));
    }

    public function testTheFloorOfAPartOfALongerPassword(): void
    {
        $characters = mb_str_split('abcdefghij');
        [$guesses, $sequence] = Search::cheapest($characters, [$this->match(0, 4, 'abcde', 3.0), $this->match(5, 9, 'fghij', 3.0)]);

        self::assertSame(2 * 50.0 * 50.0 + 10000.0, $guesses);
        self::assertSame([50.0, 50.0], array_map(static fn (PatternMatch $match): float => $match->guesses, $sequence));

        [$guesses, $sequence] = Search::cheapest(mb_str_split('xbcdefghijk'), [$this->match(0, 0, 'x', 1.0), $this->match(1, 10, 'bcdefghijk', 1.0)]);

        self::assertSame(2 * 10.0 * 50.0 + 10000.0, $guesses);
        self::assertSame([10.0, 50.0], array_map(static fn (PatternMatch $match): float => $match->guesses, $sequence));
    }

    public function testAPatternWithCharactersGuessedOneByOneAroundIt(): void
    {
        $characters = mb_str_split('xabcdefghijyz');
        [$guesses, $sequence] = Search::cheapest($characters, [$this->match(1, 10, 'abcdefghij', 60.0)]);

        self::assertSame(6 * 11.0 * 60.0 * 100.0 + 1e8, $guesses);
        self::assertSame(
            [[Pattern::Bruteforce, 0, 0, 'x', 11.0], [Pattern::Dictionary, 1, 10, 'abcdefghij', 60.0], [Pattern::Bruteforce, 11, 12, 'yz', 100.0]],
            $this->describe($sequence),
        );
    }

    public function testTheCheapestOfOverlappingPatterns(): void
    {
        $characters = mb_str_split('abcdef');
        [$guesses, $sequence] = Search::cheapest($characters, [
            $this->match(0, 2, 'abc', 900.0),
            $this->match(0, 3, 'abcd', 60.0),
            $this->match(3, 5, 'def', 60.0),
            $this->match(4, 5, 'ef', 70.0),
            $this->match(0, 5, 'abcdef', 20000.0),
        ]);

        self::assertSame(2 * 60.0 * 70.0 + 10000.0, $guesses);
        self::assertSame(['abcd', 'ef'], array_map(static fn (PatternMatch $match): string => $match->token, $sequence));
    }

    public function testFewerPatternsWinWhenTheyCostNoMore(): void
    {
        $characters = mb_str_split('abcdef');
        [$guesses, $sequence] = Search::cheapest($characters, [
            $this->match(0, 2, 'abc', 50.0),
            $this->match(3, 5, 'def', 50.0),
            $this->match(0, 5, 'abcdef', 14999.0),
        ]);

        self::assertSame(15000.0, $guesses);
        self::assertSame(['abcdef'], array_map(static fn (PatternMatch $match): string => $match->token, $sequence));
    }

    public function testARunGuessedOneByOneNeverFollowsAnother(): void
    {
        $characters = mb_str_split('abcdefgh');
        [, $sequence] = Search::cheapest($characters, [$this->match(0, 3, 'abcd', 50.0)]);

        self::assertSame(['abcd', 'efgh'], array_map(static fn (PatternMatch $match): string => $match->token, $sequence));
    }

    public function testALongRunAfterAPatternIsLeftOut(): void
    {
        // A pattern of nine characters, then 21 guessed one by one, would cost 2 × 50 × 10^21: the run is longer than
        // MAX_RUN, so the whole password guessed one by one is the cover.
        $characters = mb_str_split(str_repeat('x', 30));
        [$guesses, $sequence] = Search::cheapest($characters, [$this->match(0, 8, 'xxxxxxxxx', 1.0)]);

        self::assertSame(1e30 + 1, $guesses);
        self::assertSame([[Pattern::Bruteforce, 0, 29, str_repeat('x', 30), 1e30]], $this->describe($sequence));

        [$guesses, $sequence] = Search::cheapest(mb_str_split(str_repeat('x', 29)), [$this->match(0, 8, 'xxxxxxxxx', 1.0)]);

        self::assertSame(2 * 50.0 * 1e20 + 10000.0, $guesses);
        self::assertCount(2, $sequence);
    }

    public function testEightPatternsWhenRunsCannotReplaceThem(): void
    {
        // Eight patterns of 21 characters: a run guessed one by one can only replace them from the start, where it
        // costs at least 10^42 with six patterns after it.
        $characters = mb_str_split(str_repeat('x', 168));
        $matches = [];

        for ($i = 0; $i < 8; ++$i) {
            $matches[] = self::match(21 * $i, 21 * $i + 20, str_repeat('x', 21), 1.0);
        }

        [$guesses, $sequence] = Search::cheapest($characters, $matches);

        self::assertCount(8, $sequence);
        self::assertEqualsWithDelta(40320 * 50.0 ** 8 + 1e28, $guesses, 1e16);
    }

    public function testARunBetweenTwoPatterns(): void
    {
        [, $sequence] = Search::cheapest(mb_str_split('abcdexyfghij'), [self::match(0, 4, 'abcde', 60.0), self::match(7, 11, 'fghij', 60.0)]);

        self::assertSame(
            [[Pattern::Dictionary, 0, 4, 'abcde', 60.0], [Pattern::Bruteforce, 5, 6, 'xy', 100.0], [Pattern::Dictionary, 7, 11, 'fghij', 60.0]],
            self::describe($sequence),
        );
    }

    public function testTheFirstOfTwoEqualPatternsIsKept(): void
    {
        [, $sequence] = Search::cheapest(mb_str_split('abcd'), [
            new PatternMatch(Pattern::Dictionary, 0, 3, 'abcd', 5.0, 'first'),
            new PatternMatch(Pattern::Dictionary, 0, 3, 'abcd', 5.0, 'second'),
        ]);

        self::assertSame('first', $sequence[0]->dictionary);
    }

    public function testARunAfterALaterPatternWhenAnEarlierOneIsTooFar(): void
    {
        $characters = mb_str_split(str_repeat('x', 30));
        [$guesses, $sequence] = Search::cheapest($characters, [self::match(0, 1, 'xx', 1.0), self::match(2, 20, str_repeat('x', 19), 1.0)]);

        self::assertSame(6 * 50.0 * 50.0 * 1e9 + 1e8, $guesses);
        self::assertSame([[0, 1], [2, 20], [21, 29]], array_map(static fn (PatternMatch $match): array => [$match->start, $match->end], $sequence));
    }

    private function match(int $start, int $end, string $token, float $guesses): PatternMatch
    {
        return new PatternMatch(Pattern::Dictionary, $start, $end, $token, $guesses);
    }

    /**
     * @param list<PatternMatch> $sequence
     *
     * @return list<array{Pattern, int, int, string, float}>
     */
    private function describe(array $sequence): array
    {
        return array_map(static fn (PatternMatch $match): array => [$match->pattern, $match->start, $match->end, $match->token, $match->guesses], $sequence);
    }
}
