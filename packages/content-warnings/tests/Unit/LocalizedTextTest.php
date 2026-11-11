<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Tests\Unit;

use Phil\ContentWarnings\Exception\InvalidCatalogue;
use Phil\ContentWarnings\LocalizedText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalizedText::class)]
#[CoversClass(InvalidCatalogue::class)]
final class LocalizedTextTest extends TestCase
{
    public function testTextsAreTrimmedAndEmptyOnesDropped(): void
    {
        $text = LocalizedText::of(['en' => ' Spiders ', 'fr' => '  ', 'fr-CA' => 'Araignées', 'yue_Hant_HK' => '蜘蛛']);

        self::assertSame(['en' => 'Spiders', 'fr_CA' => 'Araignées', 'yue_Hant_HK' => '蜘蛛'], $text->toArray());
        self::assertTrue($text->has('fr_CA'));
        self::assertFalse($text->has('fr'));
        self::assertFalse($text->isEmpty());
        self::assertTrue(LocalizedText::of(['en' => ''])->isEmpty());
        self::assertSame([], LocalizedText::empty()->toArray());
    }

    public function testAnExactLocaleThenItsLanguageThenAFallbackThenTheFirstTextIsRead(): void
    {
        $text = LocalizedText::of(['de' => 'Spinnen', 'fr' => 'Araignées', 'fr_CA' => 'Bibittes', 'en' => 'Spiders']);

        self::assertSame('Bibittes', $text->in('fr_CA'));
        self::assertSame('Bibittes', $text->in('fr-CA'));
        self::assertSame('Araignées', $text->in('fr_BE'));
        self::assertSame('Araignées', $text->in('fr'));
        self::assertSame('Spiders', $text->in('ja'));
        self::assertSame('Araignées', $text->in('ja', ['it', 'fr', 'en']));
        self::assertSame('Spinnen', $text->in('ja', []));
        self::assertSame('', LocalizedText::empty()->in('fr'));
    }

    public function testAnotherTextIsMergedByLocaleAndWins(): void
    {
        $merged = LocalizedText::of(['en' => 'Gore', 'fr' => 'Gore'])->with(LocalizedText::of(['en' => 'Graphic gore', 'de' => 'Blut']));

        self::assertSame(['en' => 'Graphic gore', 'fr' => 'Gore', 'de' => 'Blut'], $merged->toArray());
    }

    public function testATextOfFiveHundredCharactersIsKept(): void
    {
        self::assertSame(500, mb_strlen(LocalizedText::of(['fr' => str_repeat('é', 500)])->in('fr')));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'no locale' => [['Spiders'], 'Invalid content warning catalogue: "0" is not a locale.'];
        yield 'a capital language' => [['EN' => 'Spiders'], '"EN" is not a locale'];
        yield 'a one-letter language' => [['e' => 'Spiders'], '"e" is not a locale'];
        yield 'a four-letter language' => [['engl' => 'Spiders'], '"engl" is not a locale'];
        yield 'a one-letter subtag' => [['en_U' => 'Spiders'], '"en_U" is not a locale'];
        yield 'a nine-letter subtag' => [['en_ABCDEFGHI' => 'Spiders'], '"en_ABCDEFGHI" is not a locale'];
        yield 'four subtags' => [['en_AA_BB_CC_DD' => 'Spiders'], '"en_AA_BB_CC_DD" is not a locale'];
        yield 'a trailing newline' => [["en\n" => 'Spiders'], 'is not a locale'];
        yield 'a long locale is cut' => [['x'.str_repeat('é', 49) => 'Spiders'], '"x'.str_repeat('é', 39).'" is not a locale'];
        yield 'a text that is no string' => [['en' => 3], 'the text in "en" is not a string'];
        yield 'a text too long' => [['fr-CA' => str_repeat('a', 501)], 'the text in "fr_CA" is over 500 characters'];
    }

    /**
     * @param array<array-key, mixed> $texts
     */
    #[DataProvider('refused')]
    public function testMalformedTextsAreRefused(array $texts, string $message): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains($message);

        LocalizedText::of($texts);
    }
}
