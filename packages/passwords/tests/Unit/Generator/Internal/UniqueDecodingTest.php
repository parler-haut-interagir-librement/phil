<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Generator\Internal;

use Phil\Passwords\Generator\Internal\UniqueDecoding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UniqueDecoding::class)]
final class UniqueDecodingTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function codes(): iterable
    {
        yield 'no codeword' => [[], true];
        yield 'one codeword' => [['abc'], true];
        yield 'a prefix code' => [['0', '10', '110', '111'], true];
        yield 'a prefix code of words and a separator' => [['week-', 'end-', 'weekend-'], true];
        yield 'two codewords that are the same' => [['ab', 'cd', 'ab'], false];
        yield 'a compound word' => [['week', 'end', 'weekend'], false];
        yield 'a compound word, the parts given last' => [['weekend', 'week', 'end'], false];
        yield 'a suffix code' => [['0', '01', '11'], true];
        yield 'a dangling suffix that comes back' => [['a', 'ab', 'bb'], true];
        yield 'a dangling suffix shorter than every codeword' => [['ab', 'abc', 'cd'], true];
        yield 'a codeword and the same with one more letter' => [['ab', 'abc', 'cab'], false];
        yield 'capitals that start each word' => [['Week', 'End', 'Weekend'], true];
        yield 'ambiguous after a dangling suffix that comes back' => [['a', 'ab', 'bb', 'bba', 'ba'], false];
        yield 'ambiguous after three steps' => [['a', 'c', 'ad', 'abb', 'bad', 'deb', 'bbcde'], false];
        yield 'ambiguous through the second of two codewords that start a codeword' => [['a', 'ab', 'abcd', 'cd'], false];
        yield 'ambiguous through the shortest codeword' => [['ab', 'abcd', 'cd'], false];
        yield 'a digit appended to a number' => [['1000', '1001', '1100', '10001', '11001'], false];
        yield 'a digit and a separator' => [['1000-', '1001-', '1100-', '10001-', '11001-'], true];
        yield 'a codeword that another one ends' => [['bbbb', 'abbb', 'bbb'], false];
        yield 'multibyte' => [['été', 'é', 'té'], false];
        yield 'multibyte, a prefix code' => [['été-', 'é-', 'té-'], true];
    }

    /**
     * @param list<string> $codewords
     */
    #[DataProvider('codes')]
    public function testTheCodes(array $codewords, bool $expected): void
    {
        self::assertSame($expected, UniqueDecoding::holds($codewords));
    }

    public function testNumericCodewords(): void
    {
        self::assertTrue(UniqueDecoding::holds(array_map(strval(...), range(1000, 1255))));
        self::assertFalse(UniqueDecoding::holds(['12', '1', '2']));
    }

    public function testAmbiguityFoundOnlyPastAVisitedSuffix(): void
    {
        // The suffix "b" is left twice — by "a" in "ab" and by "ab" in "abb" — and its second visit comes before the
        // suffix "bc" reaches the codeword "c".
        self::assertFalse(UniqueDecoding::holds(['a', 'ab', 'abb', 'bc', 'c', 'x']));
        self::assertTrue(UniqueDecoding::holds(['a', 'ab', 'abb', 'bc', 'x']));
    }
}
