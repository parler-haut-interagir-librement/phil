<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Application;

use Phil\RateLimitBundle\Application\IdentifierKey;
use Phil\RateLimitBundle\Tests\Unit\Fixture\PolyfillCalls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Polyfill\Intl\Normalizer\Normalizer;
use Symfony\Polyfill\Mbstring\Mbstring;

#[CoversClass(IdentifierKey::class)]
final class IdentifierKeyTest extends TestCase
{
    /**
     * The keys expected on every server, with the intl and mbstring extensions or with the polyfills of
     * symfony/string.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function identifiers(): iterable
    {
        yield 'as it is' => ['ada', 'ada'];
        yield 'capitals' => ['ADA', 'ada'];
        yield 'a German sharp s, folded' => ["Stra\u{00DF}e", 'strasse'];
        yield 'a capital sharp s, folded' => ["STRA\u{1E9E}E", 'strasse'];
        yield 'a composed accent' => ["\u{00C9}lodie", 'elodie'];
        yield 'a combining accent' => ["E\u{0301}lodie", 'elodie'];
        yield 'a capital I with a dot' => ["\u{0130}stanbul", 'istanbul'];
        yield 'an enclosing mark' => ["a\u{20DD}da", 'ada'];
        yield 'a full-width letter' => ["\u{FF21}da", 'ada'];
        yield 'a circled letter' => ["\u{24B6}da", 'ada'];
        yield 'a mathematical bold letter' => ["\u{1D400}da", 'ada'];
        yield 'a ligature' => ["\u{FB01}ne", 'fine'];
        yield 'a Roman numeral' => ["\u{216B}", 'xii'];
        yield 'a title-case digraph' => ["\u{01C5}emal", 'dzemal'];
        yield 'a Greek capital sigma ending a word' => ["\u{039F}\u{0394}\u{039F}\u{03A3}", "\u{03BF}\u{03B4}\u{03BF}\u{03C3}"];
        yield 'a Greek final sigma and an accent' => ["\u{0386}\u{03C1}\u{03B7}\u{03C2}", "\u{03B1}\u{03C1}\u{03B7}\u{03C3}"];
        yield 'a Cherokee capital' => ["\u{13A0}\u{13A1}", "\u{AB70}\u{AB71}"];
        yield 'a Cyrillic capital' => ["\u{0410}\u{0434}\u{0430}", "\u{0430}\u{0434}\u{0430}"];
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

    /**
     * The same keys when symfony/string runs on the polyfills rather than on the intl and mbstring extensions. The
     * polyfills come with symfony/string when the bundle is installed on its own; a checkout that replaces them, such
     * as a monorepo requiring both extensions, skips this test.
     */
    #[RunInSeparateProcess]
    public function testTheKeysAreTheSameWithThePolyfills(): void
    {
        if (!class_exists(Normalizer::class) || !class_exists(Mbstring::class)) {
            self::markTestSkipped('symfony/polyfill-intl-normalizer and symfony/polyfill-mbstring are not installed.');
        }

        require __DIR__.'/../Fixture/polyfilled-string.php';

        foreach (self::identifiers() as $name => [$identifier, $expected]) {
            self::assertSame($expected, IdentifierKey::normalize($identifier), $name);
        }

        self::assertGreaterThan(0, PolyfillCalls::$count);
    }
}
