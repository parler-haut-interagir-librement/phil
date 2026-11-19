<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Application\Code;

use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Application\Exception\InvalidCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqidCodec::class)]
#[CoversClass(InvalidCodec::class)]
final class SqidCodecTest extends TestCase
{
    /**
     * The identifiers the format gives, as its specification lists them.
     *
     * @return iterable<string, array{SqidCodec, list<int>, string}>
     */
    public static function identifiers(): iterable
    {
        yield 'default alphabet' => [new SqidCodec(), [1, 2, 3], '86Rf07'];
        yield 'one zero' => [new SqidCodec(), [0], 'bM'];
        yield 'minimum length' => [new SqidCodec(minLength: 10), [1, 2, 3], '86Rf07xd4z'];
        yield 'custom alphabet' => [new SqidCodec('FxnXM1kBN6cuhsAvjW3Co7l2RePyY8DwaU04Tzt9fHQrqSVKdpimLGIJOgb5ZE'), [1, 2, 3], 'B4aajs'];
        yield 'blocked identifier' => [new SqidCodec(blocklist: ['86Rf07']), [1, 2, 3], 'se8ojk'];
        yield 'largest integer' => [new SqidCodec(), [\PHP_INT_MAX], 'AqkYhRmbHpEX'];
    }

    /**
     * @param list<int> $numbers
     */
    #[DataProvider('identifiers')]
    public function testItWritesTheIdentifiersOfTheFormatAndReadsThemBack(SqidCodec $codec, array $numbers, string $id): void
    {
        self::assertSame($id, $codec->encode($numbers));
        self::assertSame($numbers, $codec->decode($id));
        self::assertTrue($codec->isCanonical($id));
    }

    public function testEveryListOfNumbersComesBack(): void
    {
        $codec = new SqidCodec(minLength: 8, blocklist: ['abc', 'xyz9']);

        foreach ([[0, 0], [7, 0, 999_999], [12_345_678_901, 3], range(0, 20)] as $numbers) {
            self::assertSame($numbers, $codec->decode($codec->encode($numbers)));
        }

        for ($number = 0; $number < 2_000; $number += 7) {
            $id = $codec->encode([$number]);
            self::assertGreaterThanOrEqual(8, \strlen($id));
            self::assertSame([$number], $codec->decode($id));
        }
    }

    public function testTheMinimumLengthPadsLongerThanTheAlphabet(): void
    {
        $codec = new SqidCodec('abc', 10);
        $id = $codec->encode([1]);

        self::assertSame(10, \strlen($id));
        self::assertSame([1], $codec->decode($id));
    }

    public function testNoNumbersIsTheEmptyIdentifier(): void
    {
        self::assertSame('', new SqidCodec()->encode([]));
        self::assertSame([], new SqidCodec()->decode(''));
    }

    public function testANegativeNumberIsRefused(): void
    {
        $this->expectException(InvalidCodec::class);
        $this->expectExceptionMessageIsOrContains('A number to encode is 0 or more.');

        new SqidCodec()->encode([1, -1]);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidCodecs(): iterable
    {
        yield 'two characters' => ['ab', 0, 'The alphabet has at least 3 characters'];
        yield 'a space' => ['ab c', 0, 'The alphabet has at least 3 characters'];
        yield 'a slash' => ['abc/', 0, 'The alphabet has at least 3 characters'];
        yield 'beyond ASCII' => ['abcé', 0, 'The alphabet has at least 3 characters'];
        yield 'a repeated character' => ['abca', 0, 'Each character of the alphabet appears once.'];
        yield 'a negative length' => ['abc', -1, 'The minimum length is between 0 and 255.'];
        yield 'a length too long' => ['abc', 256, 'The minimum length is between 0 and 255.'];
    }

    #[DataProvider('invalidCodecs')]
    public function testAnAlphabetOrALengthOutOfTheFormatIsRefused(string $alphabet, int $minLength, string $message): void
    {
        $this->expectException(InvalidCodec::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SqidCodec($alphabet, $minLength);
    }

    public function testTheBoundsOfTheFormatAreAccepted(): void
    {
        self::assertSame(255, \strlen(new SqidCodec('abc', 255)->encode([1])));
        self::assertSame('', new SqidCodec('a-_')->encode([]));
        self::assertSame([5], new SqidCodec('a.~')->decode(new SqidCodec('a.~')->encode([5])));
    }

    public function testAnIdentifierItCouldNotHaveWrittenDecodesToNothing(): void
    {
        $codec = new SqidCodec();

        self::assertSame([], $codec->decode('86Rf0!'), 'a character out of the alphabet');
        self::assertSame([], $codec->decode(str_repeat('a', 256)), 'longer than an identifier');
        self::assertSame([], $codec->decode(str_repeat('z', 40)), 'a number beyond PHP_INT_MAX');
        $longest = new SqidCodec(minLength: 255);
        self::assertSame([1], $longest->decode($longest->encode([1])), 'an identifier of 255 characters is read');
    }

    public function testAnIdentifierThatDecodesButIsNotTheOneWrittenIsNotCanonical(): void
    {
        $codec = new SqidCodec();

        self::assertFalse($codec->isCanonical(''));
        self::assertFalse($codec->isCanonical('!'));
        self::assertFalse($codec->isCanonical('86Rf07xd4z'), 'the padded identifier of a codec without a minimum length');
        self::assertTrue(new SqidCodec(minLength: 10)->isCanonical('86Rf07xd4z'));
    }

    public function testAnIdentifierCutAtASeparatorKeepsTheNumbersBeforeIt(): void
    {
        $codec = new SqidCodec();
        $id = $codec->encode([1, 2]);
        $padded = new SqidCodec(minLength: 12)->encode([1, 2]);

        self::assertSame([1, 2], new SqidCodec(minLength: 12)->decode($padded));
        self::assertSame([1, 2], $codec->decode($padded), 'the padding starts with a separator, after which nothing is read');
        self::assertSame([1], $codec->decode(substr($id, 0, 2)));
    }

    public function testItAcceptsTextsOfItsAlphabetWithinALength(): void
    {
        $codec = new SqidCodec('abc');

        self::assertTrue($codec->accepts('abc'));
        self::assertTrue($codec->accepts('a'));
        self::assertTrue($codec->accepts('aa', 2, 2));
        self::assertTrue($codec->accepts(str_repeat('a', 255)));
        self::assertFalse($codec->accepts(str_repeat('a', 256)));
        self::assertFalse($codec->accepts(''));
        self::assertFalse($codec->accepts('a', 2));
        self::assertFalse($codec->accepts('aaa', 1, 2));
        self::assertFalse($codec->accepts('abd'));
        self::assertFalse($codec->accepts('ABC'));
    }

    public function testTheBlocklistKeepsTheWordsTheAlphabetCanWrite(): void
    {
        $codec = new SqidCodec('abcdefghijklmnopqrstuvwxyz0123456789', blocklist: ['Bad', 'no', 'wörd', 'xy-z', 'good']);

        self::assertTrue($codec->isBlocked('BAD'), 'compared in lower case');
        self::assertTrue($codec->isBlocked('xgoodx'));
        self::assertFalse($codec->isBlocked('no'), 'a word under three characters is dropped');
        self::assertFalse($codec->isBlocked('xyzw'));
        self::assertFalse($codec->isBlocked('xy-z'), 'a word with a character out of the alphabet is dropped');
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function blockedIdentifiers(): iterable
    {
        yield 'a short identifier equal to a word' => ['sex', true];
        yield 'a short identifier containing nothing else' => ['sexy', true];
        yield 'a short word inside a longer identifier' => ['asexb', false];
        yield 'a short word equal to a longer identifier' => ['abc', false];
        yield 'a long word inside' => ['xxpornxx', true];
        yield 'a long word as a whole' => ['porn', true];
        yield 'a word with a digit at the start' => ['h4ckxx', true];
        yield 'a word with a digit at the end' => ['xxh4ck', true];
        yield 'a word with a digit in the middle' => ['xh4ckx', false];
        yield 'an identifier shorter than the word' => ['por', false];
        yield 'a three-character identifier holding a longer word' => ['h4c', false];
    }

    #[DataProvider('blockedIdentifiers')]
    public function testTheBlocklistRules(string $id, bool $blocked): void
    {
        self::assertSame($blocked, new SqidCodec(blocklist: ['sex', 'porn', 'h4ck', 'sexy'])->isBlocked($id));
    }

    public function testAThreeCharacterIdentifierEqualToALongerWordIsKept(): void
    {
        self::assertFalse(new SqidCodec(blocklist: ['abcd'])->isBlocked('abc'));
        self::assertTrue(new SqidCodec(blocklist: ['abc'])->isBlocked('abc'));
        self::assertFalse(new SqidCodec(blocklist: ['abc'])->isBlocked('abcd'), 'a word of three characters only matches whole');
    }

    public function testEveryIdentifierBlockedIsRefused(): void
    {
        $words = [];

        foreach (str_split('abc') as $first) {
            foreach (str_split('abc') as $second) {
                foreach (str_split('abc') as $third) {
                    $words[] = $first.$second.$third;
                }
            }
        }

        $this->expectException(InvalidCodec::class);
        $this->expectExceptionMessageIsOrContains('Every identifier of these numbers is blocked.');

        new SqidCodec('abc', 3, $words)->encode([0]);
    }

    public function testABlockedIdentifierMovesToTheNextOffset(): void
    {
        $codec = new SqidCodec('abc', 3);
        $first = $codec->encode([0]);
        $blocked = new SqidCodec('abc', 3, [$first]);
        $next = $blocked->encode([0]);

        self::assertNotSame($first, $next);
        self::assertSame([0], $blocked->decode($next));
    }

    public function testAnUpperCaseAlphabetKeepsTheWordsOfItsBlocklist(): void
    {
        $codec = new SqidCodec('ABCDEFGHIJKLMNOPQRSTUVWXYZ', blocklist: ['Abc']);

        self::assertTrue($codec->isBlocked('ABC'), 'the word is compared with the alphabet in lower case');
    }

    public function testAWordLongerThanTheTextDoesNotStopTheOthersFromBeingCompared(): void
    {
        self::assertTrue(new SqidCodec(blocklist: ['abcdefgh', 'xyz'])->isBlocked('xyz'));
    }

    public function testAnIdentifierAsLongAsTheMinimumLengthIsNotPadded(): void
    {
        self::assertSame('86Rf07', new SqidCodec(minLength: 6)->encode([1, 2, 3]));
    }
}
