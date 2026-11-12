<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit;

use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\TranslationMemoryException;
use Phil\TranslationMemory\LanguageTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageTag::class)]
#[CoversClass(InvalidLanguage::class)]
final class LanguageTagTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tags(): iterable
    {
        yield 'language' => ['EN', 'en'];
        yield 'region' => ['fr-ca', 'fr-CA'];
        yield 'script and region' => ['ZH-HANT-tw', 'zh-Hant-TW'];
        yield 'numeric region' => ['es-419', 'es-419'];
        yield 'variant' => ['de-CH-1996', 'de-CH-1996'];
        yield 'private use' => ['en-X-Pirate', 'en-x-pirate'];
        yield 'long language' => ['abcdefgh', 'abcdefgh'];
    }

    #[DataProvider('tags')]
    public function testWritesATagInItsCanonicalCase(string $tag, string $canonical): void
    {
        $language = LanguageTag::of($tag);

        self::assertSame($canonical, $language->value);
        self::assertSame($canonical, $language->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'one letter' => ['e'];
        yield 'nine letters' => ['abcdefghi'];
        yield 'digits first' => ['12'];
        yield 'underscore' => ['fr_CA'];
        yield 'empty subtag' => ['fr--CA'];
        yield 'long subtag' => ['fr-abcdefghi'];
        yield 'trailing hyphen' => ['fr-'];
        yield 'line feed' => ["fr\n"];
        yield 'leading text' => ['x fr'];
    }

    #[DataProvider('invalid')]
    public function testRefusesWhatIsNotATag(string $tag): void
    {
        try {
            LanguageTag::of($tag);
            self::fail('The tag was accepted.');
        } catch (InvalidLanguage $invalidLanguage) {
            self::assertInstanceOf(TranslationMemoryException::class, $invalidLanguage);
            self::assertSame(\sprintf('"%s" is not a language tag such as "en", "fr-CA" or "zh-Hant-TW".', $tag), $invalidLanguage->getMessage());
        }
    }

    public function testTwoTagsAreEqualByTheirCanonicalForm(): void
    {
        self::assertTrue(LanguageTag::of('fr-ca')->equals(LanguageTag::of('FR-CA')));
        self::assertFalse(LanguageTag::of('fr')->equals(LanguageTag::of('fr-CA')));
    }

    public function testReadsALocaleAsATag(): void
    {
        self::assertSame('fr-CA', LanguageTag::fromLocale('fr_CA')->value);
        self::assertSame('zh-Hant-TW', LanguageTag::fromLocale('zh_hant_tw')->value);
        self::assertSame('en', LanguageTag::fromLocale('en')->value);

        $this->expectException(InvalidLanguage::class);
        LanguageTag::fromLocale('fr__CA');
    }

    public function testDropsTheLastSubtagForTheParent(): void
    {
        self::assertSame('fr', LanguageTag::of('fr-ca')->parent()?->value);
        self::assertSame('zh-Hant', LanguageTag::of('zh-hant-tw')->parent()?->value);
        self::assertNull(LanguageTag::of('fr')->parent());
    }

    public function testNamesAMismatch(): void
    {
        self::assertSame('The target language is "fr", but "de" was given.', InvalidLanguage::mismatch('target', 'fr', 'de')->getMessage());
    }

    public function testNamesALanguageWithoutParent(): void
    {
        self::assertSame('"fr" is a primary language: a regional variant such as "fr-CA" has a parent to inherit from.', InvalidLanguage::noParent('fr')->getMessage());
    }
}
