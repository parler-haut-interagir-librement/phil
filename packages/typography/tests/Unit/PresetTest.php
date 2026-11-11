<?php

declare(strict_types=1);

namespace Phil\Typography\Tests\Unit;

use Phil\Typography\Exception\TypographyException;
use Phil\Typography\Exception\UnknownFixer;
use Phil\Typography\Exception\UnsupportedLanguage;
use Phil\Typography\Fixer\CurlyQuote;
use Phil\Typography\Fixer\Dash;
use Phil\Typography\Fixer\DashStyle;
use Phil\Typography\Fixer\Dimension;
use Phil\Typography\Fixer\Ellipsis;
use Phil\Typography\Fixer\FrenchSpacing;
use Phil\Typography\Fixer\Hyphen;
use Phil\Typography\Fixer\NoSpaceBeforeComma;
use Phil\Typography\Fixer\SmartQuotes;
use Phil\Typography\Fixer\SpaceBeforePunctuation;
use Phil\Typography\Fixer\Trademark;
use Phil\Typography\Fixer\UnicodeNormalization;
use Phil\Typography\Fixer\Unit;
use Phil\Typography\Fixer\WordBreak;
use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Hyphenation\Hyphenator;
use Phil\Typography\Locale;
use Phil\Typography\Preset;
use Phil\Typography\Quote\QuoteStyles;
use Phil\Typography\Text\Run;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preset::class)]
#[CoversClass(UnknownFixer::class)]
final class PresetTest extends TestCase
{
    private const array COMMON = ['unicode_normalization', 'ellipsis', 'dimension', 'dash', 'smart_quotes', 'curly_quote', 'no_space_before_comma'];

    public function testTheNamesOfALocale(): void
    {
        self::assertSame([...self::COMMON, 'trademark', 'unit', 'hyphen'], Preset::names(Locale::parse('en_US')));
        self::assertSame([...self::COMMON, 'space_before_punctuation', 'trademark', 'unit', 'hyphen'], Preset::names(Locale::parse('fr_CA')));
        self::assertSame([...self::COMMON, 'space_before_punctuation', 'trademark', 'unit'], Preset::names(Locale::parse('fr'), false));
        self::assertSame([...self::COMMON, 'trademark', 'unit', 'hyphen'], Preset::names(Locale::parse('hu_HU')));
        self::assertSame([...self::COMMON, 'trademark', 'unit'], Preset::names(Locale::parse('zu_ZA')));
        self::assertSame([...self::COMMON, 'trademark', 'unit'], Preset::names(Locale::parse('de'), true, new Dictionary('/nonexistent')));
        self::assertSame([...self::COMMON, 'trademark', 'unit', 'word_break'], Preset::names(Locale::parse('th_TH')));
        self::assertSame([...self::COMMON, 'trademark', 'unit'], Preset::names(Locale::parse('th'), false));
        self::assertSame(Preset::FIXERS, [...self::COMMON, 'space_before_punctuation', 'trademark', 'unit', 'hyphen', 'word_break']);
        self::assertEquals(new WordBreak(), Preset::fixer('word_break', Locale::parse('th')));
    }

    public function testTheFixersAreSetForTheLocale(): void
    {
        $locale = Locale::parse('fr_CA');

        self::assertEquals([
            new UnicodeNormalization(),
            new Ellipsis(),
            new Dimension(),
            new Dash(DashStyle::Spaced),
            new SmartQuotes(QuoteStyles::for($locale)),
            new CurlyQuote(),
            new NoSpaceBeforeComma(),
            new SpaceBeforePunctuation(FrenchSpacing::Canada),
            new Trademark(),
            new Unit(),
            new Hyphen(Hyphenator::for('fr')),
        ], Preset::fixers($locale));
        self::assertEquals(new Dash(DashStyle::Closed), Preset::fixer('dash', Locale::parse('en')));
        self::assertEquals(new SpaceBeforePunctuation(FrenchSpacing::France), Preset::fixer('space_before_punctuation', Locale::parse('fr_FR')));
        self::assertCount(9, Preset::fixers(Locale::parse('en'), false));
    }

    public function testTheFixersUseTheDictionaryGiven(): void
    {
        $directory = sys_get_temp_dir().'/phil-typography-preset-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/fr.pat.txt', '1a1 1b1');

        try {
            $fixers = Preset::fixers(Locale::parse('fr'), true, new Dictionary($directory));

            self::assertCount(11, $fixers);
            self::assertSame(["ab\u{AD}a\u{AD}ba"], $fixers[10]->fix(Run::of('ababa'))->texts);
            self::assertSame(["ab\u{AD}a\u{AD}ba"], Preset::fixer('hyphen', Locale::parse('fr'), new Dictionary($directory))->fix(Run::of('ababa'))->texts);
        } finally {
            unlink($directory.'/fr.pat.txt');
            rmdir($directory);
        }
    }

    public function testAnUnknownName(): void
    {
        try {
            Preset::fixer('kerning', Locale::parse('en'));
            self::fail('kerning');
        } catch (UnknownFixer $unknownFixer) {
            self::assertInstanceOf(TypographyException::class, $unknownFixer);
            self::assertSame('There is no fixer named "kerning"; the fixers are: '.implode(', ', Preset::FIXERS).'.', $unknownFixer->getMessage());
        }
    }

    public function testHyphenationWithoutPatterns(): void
    {
        $this->expectException(UnsupportedLanguage::class);

        Preset::fixer('hyphen', Locale::parse('th'));
    }
}
