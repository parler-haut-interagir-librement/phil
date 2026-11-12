<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Language;

use Phil\AccessibilityCheck\Language\LanguageTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageTag::class)]
final class LanguageTagTest extends TestCase
{
    /**
     * @return iterable<array{string, bool}>
     */
    public static function tags(): iterable
    {
        foreach (['fr', 'FR-ca', 'en-US', 'zh-Hant-TW', 'sr-Latn-RS', 'es-419', 'de-CH-1901', 'sl-rozaj-biske', 'zh-yue', 'en-a-bbb-x-ccc', 'x-private', 'en-x-a-b', 'haw', 'abcde', 'de-1996'] as $tag) {
            yield $tag => [$tag, true];
        }

        foreach (['', 'f', 'english!', 'fr ca', 'en_US', 'en-', 'fr--ca', 'abcdefghi', 'i-klingon', 'en-a', 'x-', 'en-x-abcdefghi', '12', 'fr-ca '] as $tag) {
            yield 'not '.$tag => [$tag, false];
        }
    }

    #[DataProvider('tags')]
    public function testATagIsWellFormedOrNot(string $tag, bool $wellFormed): void
    {
        self::assertSame($wellFormed, LanguageTag::isWellFormed($tag));
    }

    public function testThePrimaryLanguageIsTheFirstSubtagInLowerCase(): void
    {
        self::assertSame('fr', LanguageTag::primary('FR-ca'));
        self::assertSame('pt', LanguageTag::primary('pt_BR'));
        self::assertSame('de', LanguageTag::primary('de'));
    }
}
