<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions\Tests\Unit;

use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locale::class)]
#[CoversClass(InvalidLocaleDimension::class)]
final class LocaleTest extends TestCase
{
    public function testALanguageAloneIsALocale(): void
    {
        $locale = Locale::fromString('fr');

        self::assertSame('fr', $locale->value);
        self::assertSame('fr', $locale->language);
        self::assertNull($locale->region);
    }

    public function testTheLanguageTagJoinsTheRegionWithAHyphen(): void
    {
        self::assertSame('fr-CA', Locale::fromString('fr_CA')->toLanguageTag());
        self::assertSame('fil', Locale::fromString('fil')->toLanguageTag());
        self::assertSame('fil-PH', Locale::fromString('fil_PH')->toLanguageTag());
    }

    public function testALanguageAndARegionIsALocale(): void
    {
        $locale = Locale::fromString('fr_CA');

        self::assertSame('fr_CA', $locale->toString());
        self::assertSame('fr_CA', (string) $locale);
        self::assertSame('fr', $locale->language);
        self::assertSame('CA', $locale->region);
    }

    public function testAThreeLetterLanguageIsAccepted(): void
    {
        self::assertSame('haw', Locale::fromString('haw_US')->language);
    }

    /**
     * One spelling only: rows are found by equality on the string, so every other spelling of the same locale
     * would be a row that a lookup misses.
     */
    #[DataProvider('refusedSpellings')]
    public function testItRefuses(string $value): void
    {
        $this->expectException(InvalidLocaleDimension::class);

        Locale::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedSpellings(): iterable
    {
        yield 'empty' => [''];
        yield 'hyphen' => ['fr-CA'];
        yield 'lowercase region' => ['fr_ca'];
        yield 'uppercase language' => ['FR_CA'];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'trailing newline' => ["fr_CA\n"];
        yield 'script subtag' => ['zh_Hant_TW'];
    }

    public function testTheMessageNamesTheRefusedValue(): void
    {
        $this->expectExceptionMessageIsOrContains('"fr-CA" is not a locale of the form "fr" or "fr_CA".');

        Locale::fromString('fr-CA');
    }

    public function testTwoRegionsOfOneLanguageShareTheLanguage(): void
    {
        self::assertTrue(Locale::fromString('fr_CA')->sharesLanguageWith(Locale::fromString('fr')));
    }

    public function testOneRegionInTwoLanguagesDoesNotShareTheLanguage(): void
    {
        self::assertFalse(Locale::fromString('fr_CA')->sharesLanguageWith(Locale::fromString('en_CA')));
    }

    public function testEqualityIsOnTheWholeLocale(): void
    {
        self::assertTrue(Locale::fromString('fr_CA')->equals(Locale::fromString('fr_CA')));
        self::assertFalse(Locale::fromString('fr_CA')->equals(Locale::fromString('fr')));
    }
}
