<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Dictionary;
use Phil\Passwords\Strength\Internal\DictionaryMatcher;
use Phil\Passwords\Strength\Pattern;
use Phil\Passwords\Strength\PatternMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DictionaryMatcher::class)]
final class DictionaryMatcherTest extends TestCase
{
    public function testWordsAsWrittenWithTheirCase(): void
    {
        self::assertSame(
            [
                ['Pass', 1, 4, 't', 'pass', 2, false, [], 4.0],
                ['PassWord', 1, 8, 't', 'password', 1, false, [], 36.0],
                ['Word', 5, 8, 't', 'word', 3, false, [], 6.0],
                ['Word', 5, 8, 'u', 'word', 1, false, [], 2.0],
            ],
            $this->find('xPassWordx'),
        );
        self::assertSame(Pattern::Dictionary, $this->matcher()->matches(['a', 'b', 'c'])[0]->pattern);
    }

    public function testReversedWordsCostTwiceAndPalindromesOnce(): void
    {
        self::assertSame(
            [
                ['ssap', 4, 7, 't', 'pass', 2, true, [], 4.0],
                ['drowssap', 0, 7, 't', 'password', 1, true, [], 2.0],
                ['drow', 0, 3, 't', 'word', 3, true, [], 6.0],
                ['drow', 0, 3, 'u', 'word', 1, true, [], 2.0],
            ],
            $this->find('drowssap'),
        );
        self::assertSame([['level', 0, 4, 't', 'level', 8, false, [], 8.0]], $this->find('level'));
        self::assertSame([['CBA', 0, 2, 't', 'abc', 4, true, [], 16.0]], $this->find('CBA'));
    }

    public function testSubstitutions(): void
    {
        self::assertSame(
            [
                ['P4$$', 0, 3, 't', 'pass', 2, false, ['4' => 'a', '$' => 's'], 16.0],
                ['w0rd', 4, 7, 't', 'word', 3, false, ['0' => 'o'], 6.0],
                ['w0rd', 4, 7, 'u', 'word', 1, false, ['0' => 'o'], 2.0],
                ['P4$$w0rd', 0, 7, 't', 'password', 1, false, ['4' => 'a', '$' => 's', '0' => 'o'], 16.0],
            ],
            $this->sorted($this->find('P4$$w0rd')),
        );
    }

    public function testACharacterThatStandsForTwoLetters(): void
    {
        self::assertSame(
            [
                ['l10n', 0, 3, 't', 'lion', 6, false, ['1' => 'i', '0' => 'o'], 24.0],
            ],
            $this->find('l10n'),
        );
        self::assertSame(
            [
                ['|1on', 0, 3, 't', 'lion', 6, false, ['|' => 'l', '1' => 'i'], 24.0],
                ['|1o', 0, 2, 't', 'ilo', 7, false, ['|' => 'i', '1' => 'l'], 28.0],
            ],
            $this->find('|1on'),
        );
    }

    public function testASubstitutedSingleCharacterIsNoWord(): void
    {
        $matcher = DictionaryMatcher::of([Dictionary::of('t', ['a'])]);

        self::assertSame([], $matcher->matches(['@']));
        self::assertSame([['a', 0, 0]], array_map(static fn (PatternMatch $match): array => [$match->token, $match->start, $match->end], $matcher->matches(['a'])));
    }

    public function testEveryReadingOfTheAmbiguousCharacters(): void
    {
        $matches = DictionaryMatcher::of([Dictionary::of('t', ['lti', 'itl', 'ill', 'iii'])])->matches(mb_str_split('17|'));

        $words = array_map(static fn (PatternMatch $match): string => (string) $match->word, $matches);
        sort($words);

        self::assertSame(['ill', 'itl', 'lti'], $words);
        self::assertSame(['17|'], array_values(array_unique(array_map(static fn (PatternMatch $match): string => $match->token, $matches))));
    }

    public function testMoreDictionariesAndPrefixesShared(): void
    {
        $matcher = DictionaryMatcher::of([Dictionary::of('a', ['ab'])])->with(Dictionary::of('b', ['abc', 'ab']));

        self::assertSame(
            [['ab', 'a', 1], ['ab', 'b', 2], ['abc', 'b', 1]],
            array_map(static fn (PatternMatch $match): array => [$match->token, $match->dictionary, $match->rank], $matcher->matches(mb_str_split('abc'))),
        );
        self::assertSame([], DictionaryMatcher::of([])->matches(mb_str_split('abc')));
    }

    public function testDigitEntries(): void
    {
        $matches = DictionaryMatcher::of([Dictionary::of('p', ['123456', '1234'])])->matches(mb_str_split('123456'));

        self::assertSame(['1234', '123456'], array_map(static fn (PatternMatch $match): string => $match->token, $matches));
    }

    public function testWordsOfEveryScript(): void
    {
        $matcher = DictionaryMatcher::of([Dictionary::of('t', ['été', 'noël', 'level', 'pass', 'password'])]);
        $describe = static fn (string $password): array => array_map(
            static fn (PatternMatch $match): array => [$match->token, $match->start, $match->end, $match->word, $match->reversed],
            $matcher->matches(mb_str_split($password)),
        );

        self::assertSame([['ÉTÉ', 0, 2, 'été', false]], $describe('ÉTÉ'));
        self::assertSame([['lëon', 0, 3, 'noël', true]], $describe('lëon'));
        self::assertSame([['level', 4, 8, 'level', false], ['ssap', 0, 3, 'pass', true]], $describe('ssaplevel'));
    }

    public function testASubstitutionAfterThePlainStartOfAWord(): void
    {
        $matches = DictionaryMatcher::of([Dictionary::of('t', ['pass', 'password'])])->matches(mb_str_split('passw0rd'));

        self::assertSame(
            [['pass', []], ['passw0rd', ['0' => 'o']]],
            array_map(static fn (PatternMatch $match): array => [$match->token, $match->substitutions], $matches),
        );
    }

    public function testAMatchFoundByTwoTablesIsKeptOnce(): void
    {
        // `1` stands for `i` in one table and `l` in the other: `p@ss` is found by both.
        $matches = DictionaryMatcher::of([Dictionary::of('t', ['pass'])])->matches(mb_str_split('p@ss1'));

        self::assertSame(['p@ss'], array_map(static fn (PatternMatch $match): string => $match->token, $matches));
    }

    private function matcher(): DictionaryMatcher
    {
        return DictionaryMatcher::of([
            Dictionary::of('t', ['password', 'pass', 'word', 'abc', 'alpha', 'lion', 'ilo', 'level']),
            Dictionary::of('u', ['word']),
        ]);
    }

    /**
     * @return list<array{string, int, int, ?string, ?string, int, bool, array<array-key, string>, float}>
     */
    private function find(string $password): array
    {
        return array_map(
            static fn (PatternMatch $match): array => [$match->token, $match->start, $match->end, $match->dictionary, $match->word, $match->rank, $match->reversed, $match->substitutions, $match->guesses],
            $this->matcher()->matches(mb_str_split($password)),
        );
    }

    /**
     * @param list<array{string, int, int, ?string, ?string, int, bool, array<array-key, string>, float}> $matches
     *
     * @return list<array{string, int, int, ?string, ?string, int, bool, array<array-key, string>, float}>
     */
    private function sorted(array $matches): array
    {
        usort($matches, static fn (array $a, array $b): int => [$a[2] - $a[1], $a[1]] <=> [$b[2] - $b[1], $b[1]]);

        return $matches;
    }
}
