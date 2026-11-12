<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Measure;

use Phil\AnalyticsBundle\Domain\Measure\SearchTerm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchTerm::class)]
final class SearchTermTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function terms(): iterable
    {
        yield 'lower case' => ['DICE', 'dice'];
        yield 'accented lower case' => ['ÉTÉ', 'été'];
        yield 'spaces collapsed' => ["  dice \t  tower ", 'dice tower'];
        yield 'shortest' => ['ab', 'ab'];
        yield 'longest' => [str_repeat('a', 64), str_repeat('a', 64)];
        yield 'five digits' => ['12345', '12345'];
        yield 'a letter whose UTF-8 holds a byte of a C1 control' => ['ĀBC', 'ābc'];
        yield 'as many bytes as a counter holds' => [str_repeat('é', 64), str_repeat('é', 64)];
    }

    #[DataProvider('terms')]
    public function testATermIsNormalized(string $text, string $expected): void
    {
        self::assertSame($expected, SearchTerm::valueOf($text));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notCounted(): iterable
    {
        yield 'too short' => ['a'];
        yield 'one character of two bytes' => ['é'];
        yield 'spaces only' => ['    '];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'too many bytes' => [str_repeat('é', 65)];
        yield 'more bytes than a counter holds' => [str_repeat('€', 43)];
        yield 'an address' => ['alice@example.org'];
        yield 'a link' => ['https://example.org'];
        yield 'six digits' => ['order 123456'];
        yield 'a phone number' => ['5145551234'];
        yield 'invalid UTF-8' => ["ab\xFF"];
        yield 'a control character' => ["ab\x07c"];
    }

    #[DataProvider('notCounted')]
    public function testATermThatMustNotBeCounted(string $text): void
    {
        self::assertNull(SearchTerm::valueOf($text));
    }
}
