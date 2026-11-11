<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Internal\SequenceMatcher;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SequenceMatcher::class)]
final class SequenceMatcherTest extends TestCase
{
    public function testSequencesUpAndDownWithTheirGuesses(): void
    {
        $matches = SequenceMatcher::matches(mb_str_split('abcxfedcbz13579ZYX'));

        self::assertSame(
            [
                ['abc', 0, 2, 1, 12.0],
                ['fedcb', 4, 8, -1, 260.0],
                ['13579', 10, 14, 2, 20.0],
                ['ZYX', 15, 17, -1, 24.0],
            ],
            array_map(static fn (PatternMatch $match): array => [$match->token, $match->start, $match->end, $match->step, $match->guesses], $matches),
        );
        self::assertSame(Pattern::Sequence, $matches[0]->pattern);
    }

    public function testDigitsAndOtherStarts(): void
    {
        $guesses = static fn (string $password): array => array_map(static fn (PatternMatch $match): float => $match->guesses, SequenceMatcher::matches(mb_str_split($password)));

        self::assertSame([30.0], $guesses('345'));
        self::assertSame([12.0], $guesses('012'));
        self::assertSame([24.0], $guesses('987'));
        self::assertSame([24.0], $guesses('zyx'));
        self::assertSame([12.0], $guesses('ABC'));
        self::assertSame([78.0], $guesses('klm'));
        self::assertSame([78.0], $guesses('αβγ'));
    }

    public function testStepsOfZeroOrAboveFiveAndShortRunsAreNotSequences(): void
    {
        self::assertSame([], SequenceMatcher::matches(mb_str_split('aaa')));
        self::assertSame([], SequenceMatcher::matches(mb_str_split('agm')));
        self::assertSame([], SequenceMatcher::matches(mb_str_split('ab')));
        self::assertCount(1, SequenceMatcher::matches(mb_str_split('afk')));
        self::assertCount(1, SequenceMatcher::matches(mb_str_split('kfa')));
    }

    public function testTwoSequencesShareTheirBoundary(): void
    {
        $matches = SequenceMatcher::matches(mb_str_split('abcdcba'));

        self::assertSame([[0, 3], [3, 6]], array_map(static fn (PatternMatch $match): array => [$match->start, $match->end], $matches));
    }

    public function testARunOfTwoAfterOthersIsNoSequence(): void
    {
        self::assertSame([], SequenceMatcher::matches(mb_str_split('a1ab')));
        self::assertSame([], SequenceMatcher::matches(mb_str_split('a1abz9')));
    }
}
