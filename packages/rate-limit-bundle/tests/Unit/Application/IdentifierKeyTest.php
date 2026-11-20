<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Application;

use Phil\RateLimitBundle\Application\IdentifierKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentifierKey::class)]
final class IdentifierKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function identifiers(): iterable
    {
        yield 'as it is' => ['ada', 'ada'];
        yield 'capitals' => ['ADA', 'ada'];
        yield 'a German sharp s, folded' => ["Stra\u{00DF}e", 'strasse'];
        yield 'a composed accent' => ["\u{00C9}lodie", 'elodie'];
        yield 'a combining accent' => ["E\u{0301}lodie", 'elodie'];
        yield 'an enclosing mark' => ["a\u{20DD}da", 'ada'];
        yield 'a full-width letter' => ["\u{FF21}da", 'ada'];
        yield 'a ligature' => ["\u{FB01}ne", 'fine'];
        yield 'a zero-width space' => ["a\u{200B}da", 'ada'];
        yield 'a direction mark' => ["\u{200F}ada", 'ada'];
        yield 'a soft hyphen' => ["a\u{00AD}da", 'ada'];
        yield 'a control character' => ["a\u{0007}da", 'ada'];
        yield 'surrounding white space' => [" \tada\n", 'ada'];
        yield 'inner white space kept' => ['ada lovelace', 'ada lovelace'];
        yield 'invalid UTF-8, lower-cased byte by byte' => [" ADA\xFF ", "ada\xFF"];
    }

    #[DataProvider('identifiers')]
    public function testAnIdentifierIsNormalizedAsLoginThrottlingDoes(string $identifier, string $expected): void
    {
        self::assertSame($expected, IdentifierKey::normalize($identifier));
    }
}
