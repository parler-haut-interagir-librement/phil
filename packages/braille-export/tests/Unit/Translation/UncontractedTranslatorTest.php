<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Translation;

use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\UnsupportedTranslation;
use Phil\BrailleExport\Exception\UntranslatableCharacter;
use Phil\BrailleExport\Tests\Unit\Fixture\Ascii;
use Phil\BrailleExport\Translation\Braille;
use Phil\BrailleExport\Translation\Internal\Grade1;
use Phil\BrailleExport\Translation\UncontractedTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UncontractedTranslator::class)]
#[CoversClass(Grade1::class)]
#[CoversClass(UntranslatableCharacter::class)]
#[CoversClass(UnsupportedTranslation::class)]
final class UncontractedTranslatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function french(): iterable
    {
        yield 'letters' => ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'];
        yield 'accented letters' => ['àâçèéêëîïôùûüœ', '(*&!=<$%]?):\\['];
        yield 'a capital' => ['Le', '.LE'];
        yield 'a word in capitals' => ['ÉTÉ', '..=T='];
        yield 'a single capital letter' => ['A', '.A'];
        yield 'mixed capitals' => ['McDo', '.MC.DO'];
        yield 'a number in the Antoine notation' => ['1234567890', ',*<%?:$]\\[#'];
        yield 'a decimal comma' => ['3,5', ',%1:'];
        yield 'a decimal point' => ['3.5', ',%4:'];
        yield 'a comma after a number' => ['3, 5', ',%1 ,:'];
        yield 'a point at the end of a number' => ['3.', ',%4'];
        yield 'a letter after a number' => ['2e', ',<E'];
        yield 'punctuation' => [',;:.?!"«»“”()\'’-‐*@', "1234567777780''--9>"];
        yield 'dashes' => ['– —', '-- --'];
        yield 'an ellipsis' => ['…', '444'];
        yield 'a folded letter' => ['ñ', 'N'];
        yield 'a folded letter of two' => ['Æ', '.AE'];
        yield 'spaces' => ['a b', 'A B'];
        yield 'a character without braille' => ['a€b', 'AB'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function english(): iterable
    {
        yield 'letters' => ['abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'];
        yield 'a capital' => ['Hello', ',HELLO'];
        yield 'a word in capitals' => ['NASA', ',,NASA'];
        yield 'mixed capitals' => ['PhD', ',PH,D'];
        yield 'a number' => ['1234567890', '#ABCDEFGHIJ'];
        yield 'a letter a to j after a number' => ['3b', '#C;B'];
        yield 'a letter after j after a number' => ['3k', '#CK'];
        yield 'a capital after a number' => ['3B', '#C,B'];
        yield 'a decimal point' => ['1,000.5', '#A1JJJ4E'];
        yield 'a comma after a number before a letter' => ['1,a', '#A1A'];
        yield 'a word in capitals after another word' => ['a NASA', 'A ,,NASA'];
        yield 'punctuation' => [",;:.?!'’-‐", "123486''--"];
        yield 'signs of two cells' => ['‘–()[]{}“”"/\\&@#%*+=<>$€£_~', ',8,-"<">.<.>_<_>^8^0,7_/_*@&@A_?.0"9"6"7@<@>@S@E@L.-@9'];
        yield 'a long dash' => ['—', '",-'];
        yield 'an ellipsis' => ['…', '444'];
        yield 'accented letters' => ['áàâäãçéèêëíìîïñóòôöõúùûüýÿ', '^/A^*A^%A^3A^#A^&C^/E^*E^%E^3E^/I^*I^%I^3I^#N^/O^*O^%O^3O^#O^/U^*U^%U^3U^/Y^3Y'];
        yield 'a capital accented letter' => ['É', ',^/E'];
        yield 'ligatures' => ['œæß', 'OEAESS'];
        yield 'a folded letter' => ['ø', 'O'];
    }

    #[DataProvider('french')]
    public function testFrench(string $text, string $braille): void
    {
        self::assertSame([$braille], Ascii::rows(new UncontractedTranslator()->translate([$text], 'fr', Contraction::Uncontracted)));
    }

    #[DataProvider('english')]
    public function testEnglish(string $text, string $braille): void
    {
        self::assertSame([$braille], Ascii::rows(new UncontractedTranslator()->translate([$text], 'en', Contraction::Uncontracted)));
    }

    public function testEachTextIsTranslated(): void
    {
        self::assertSame(['A', 'B', ''], Ascii::rows(new UncontractedTranslator()->translate(['a', 'b', ''], 'en', Contraction::Uncontracted)));
        self::assertSame([], new UncontractedTranslator()->translate([], 'en', Contraction::Uncontracted));
    }

    public function testASpaceIsTheBlankCell(): void
    {
        self::assertSame([Braille::BLANK], new UncontractedTranslator()->translate([' '], 'fr', Contraction::Uncontracted));
    }

    public function testTheLanguageIsReadFromItsPrimarySubtag(): void
    {
        $translator = new UncontractedTranslator();

        foreach (['fr', 'FR', 'fr-CA', 'fr_BE', 'en', 'en-GB', 'EN_us'] as $language) {
            self::assertTrue($translator->supports($language, Contraction::Uncontracted), $language);
        }

        self::assertSame(['='], Ascii::rows($translator->translate(['é'], 'fr_CA', Contraction::Uncontracted)));
        self::assertSame(['^/E'], Ascii::rows($translator->translate(['é'], 'EN-au', Contraction::Uncontracted)));
        self::assertFalse($translator->supports('de', Contraction::Uncontracted));
        self::assertFalse($translator->supports('fra', Contraction::Uncontracted));
        self::assertFalse($translator->supports('', Contraction::Uncontracted));
        self::assertFalse($translator->supports('fr', Contraction::Contracted));
    }

    public function testContractedBrailleIsNotWritten(): void
    {
        $this->expectException(UnsupportedTranslation::class);
        $this->expectExceptionMessageIsOrContains('No translator writes contracted braille for the language "fr".');

        new UncontractedTranslator()->translate(['a'], 'fr', Contraction::Contracted);
    }

    public function testAnUnknownLanguageIsNotWritten(): void
    {
        $this->expectException(UnsupportedTranslation::class);
        $this->expectExceptionMessageIsOrContains('No translator writes uncontracted braille for the language "de".');

        new UncontractedTranslator()->translate(['a'], 'de', Contraction::Uncontracted);
    }

    public function testAStrictTranslatorStopsOnACharacterWithoutBraille(): void
    {
        try {
            new UncontractedTranslator(strict: true)->translate(['a€'], 'fr', Contraction::Uncontracted);
            self::fail('The euro sign has no French braille here.');
        } catch (UntranslatableCharacter $untranslatableCharacter) {
            self::assertSame('€', $untranslatableCharacter->character);
            self::assertSame('fr', $untranslatableCharacter->language);
            self::assertSame('The character U+20AC has no braille in the uncontracted table of "fr".', $untranslatableCharacter->getMessage());
        }
    }

    public function testAStrictTranslatorWritesWhatItKnows(): void
    {
        self::assertSame(['#A ,A'], Ascii::rows(new UncontractedTranslator(strict: true)->translate(['1 A'], 'en', Contraction::Uncontracted)));
    }

    public function testAStrictEnglishTranslatorStopsOnACombiningAccent(): void
    {
        $this->expectException(UntranslatableCharacter::class);
        $this->expectExceptionMessageIsOrContains('U+0301');

        new UncontractedTranslator(strict: true)->translate(["e\u{301}"], 'en', Contraction::Uncontracted);
    }
}
