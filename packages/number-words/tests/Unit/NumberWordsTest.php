<?php

declare(strict_types=1);

namespace Phil\NumberWords\Tests\Unit;

use Phil\NumberWords\CurrencyName;
use Phil\NumberWords\Exception\InvalidAmount;
use Phil\NumberWords\Exception\MissingRule;
use Phil\NumberWords\Exception\NumberOutOfRange;
use Phil\NumberWords\Exception\UnsupportedLocale;
use Phil\NumberWords\Gender;
use Phil\NumberWords\Language\AbstractLanguageRules;
use Phil\NumberWords\Language\English;
use Phil\NumberWords\Language\French;
use Phil\NumberWords\Language\German;
use Phil\NumberWords\Language\Italian;
use Phil\NumberWords\Language\Portuguese;
use Phil\NumberWords\Language\Spanish;
use Phil\NumberWords\NumberWords;
use Phil\NumberWords\SpellingOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NumberWords::class)]
#[CoversClass(AbstractLanguageRules::class)]
#[CoversClass(English::class)]
#[CoversClass(Spanish::class)]
#[CoversClass(German::class)]
#[CoversClass(Italian::class)]
#[CoversClass(Portuguese::class)]
#[CoversClass(CurrencyName::class)]
#[CoversClass(InvalidAmount::class)]
#[CoversClass(MissingRule::class)]
#[CoversClass(NumberOutOfRange::class)]
#[CoversClass(UnsupportedLocale::class)]
final class NumberWordsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, ?Gender, string}>
     */
    public static function cardinals(): iterable
    {
        yield 'English' => ['en', 21, null, 'twenty-one'];
        yield 'English has no gender' => ['en', 21, Gender::Feminine, 'twenty-one'];
        yield 'English negative' => ['en', -1234, null, 'minus one thousand two hundred thirty-four'];
        yield 'Spanish counting' => ['es', 21, null, 'veintiuno'];
        yield 'Spanish before a masculine noun' => ['es', 21, Gender::Masculine, 'veintiún'];
        yield 'Spanish before a feminine noun' => ['es', 21, Gender::Feminine, 'veintiuna'];
        yield 'Spanish soft hyphens left out' => ['es', 200, null, 'doscientos'];
        yield 'German counting' => ['de', 1, null, 'eins'];
        yield 'German neuter' => ['de', 1, Gender::Neuter, 'ein'];
        yield 'German feminine' => ['de', 1, Gender::Feminine, 'eine'];
        yield 'German soft hyphens left out' => ['de', 120, null, 'einhundertzwanzig'];
        yield 'Italian counting' => ['it', 23, null, 'ventitré'];
        yield 'Italian before a noun' => ['it', 1, Gender::Masculine, 'un'];
        yield 'Portuguese feminine' => ['pt', 222, Gender::Feminine, 'duzentas e vinte e duas'];
        yield 'European Portuguese long scale' => ['pt_PT', 2_000_000_000, null, 'dois mil milhões'];
        yield 'Brazilian Portuguese short scale' => ['pt_BR', 2_000_000_000, null, 'dois bilhões'];
        yield 'Swiss French' => ['fr_CH', 81, null, 'huitante et un'];
        yield 'Dutch, without rules of its own' => ['nl', 21, null, 'eenentwintig'];
    }

    #[DataProvider('cardinals')]
    public function testCardinals(string $locale, int $number, ?Gender $gender, string $expected): void
    {
        self::assertSame($expected, new NumberWords($locale)->cardinal($number, $gender));
    }

    public function testSoftHyphensAreKeptOnRequest(): void
    {
        $words = new NumberWords('de', new SpellingOptions(softHyphens: true));

        self::assertSame("ein\u{AD}hundert\u{AD}zwanzig", $words->cardinal(120));
        self::assertSame("ein\u{AD}und\u{AD}zwanzigste", $words->ordinal(21));
    }

    public function testVerboseEnglishSaysAnd(): void
    {
        $words = new NumberWords('en_GB', new SpellingOptions(verbose: true));

        self::assertSame('one hundred and twenty', $words->cardinal(120));
        self::assertSame('one hundred and twenty', $words->cardinal(120, Gender::Masculine));
        self::assertSame('one hundred and first', $words->ordinal(101));
    }

    public function testAVerboseFormTheLanguageLacksIsRefused(): void
    {
        $this->expectExceptionObject(MissingRule::ruleSet('fr', '%spellout-numbering-verbose'));

        new NumberWords('fr', new SpellingOptions(verbose: true))->cardinal(1);
    }

    public function testAGenderTheLanguageLacksIsRefused(): void
    {
        $this->expectExceptionObject(MissingRule::ruleSet('fr', '%spellout-cardinal-neuter'));

        new NumberWords('fr')->cardinal(1, Gender::Neuter);
    }

    /**
     * @return iterable<string, array{string, int, ?Gender, bool, string}>
     */
    public static function ordinals(): iterable
    {
        yield 'English' => ['en', 21, null, false, 'twenty-first'];
        yield 'English zero' => ['en', 0, null, false, 'zeroth'];
        yield 'English has no gender' => ['en', 2, Gender::Feminine, false, 'second'];
        yield 'German plain' => ['de', 21, null, false, 'einundzwanzigste'];
        yield 'French masculine plural' => ['fr', 1, null, true, 'premiers'];
        yield 'Spanish masculine by default' => ['es', 21, null, false, 'vigésimo primero'];
        yield 'Spanish feminine' => ['es', 3, Gender::Feminine, false, 'tercera'];
        yield 'Spanish feminine plural' => ['es', 3, Gender::Feminine, true, 'terceras'];
        yield 'German plural' => ['de', 3, null, true, 'dritte'];
        yield 'Finnish plain' => ['fi', 3, null, false, 'kolmas'];
        yield 'Finnish plural' => ['fi', 3, null, true, 'kolmannet'];
        yield 'Italian' => ['it', 21, null, false, 'ventunesimo'];
        yield 'Portuguese feminine' => ['pt', 21, Gender::Feminine, false, 'vigésima primeira'];
    }

    #[DataProvider('ordinals')]
    public function testOrdinals(string $locale, int $number, ?Gender $gender, bool $plural, string $expected): void
    {
        self::assertSame($expected, new NumberWords($locale)->ordinal($number, $gender, $plural));
    }

    public function testAPluralOrdinalTheLanguageLacksIsRefused(): void
    {
        $this->expectExceptionObject(MissingRule::ruleSet('en', '%spellout-ordinal-plural'));

        new NumberWords('en')->ordinal(1, plural: true);
    }

    public function testANegativeOrdinalIsRefused(): void
    {
        $this->expectExceptionObject(NumberOutOfRange::negativeOrdinal(-1));

        new NumberWords('en')->ordinal(-1);
    }

    public function testANumberBeyondWordsIsRefused(): void
    {
        $this->expectExceptionObject(NumberOutOfRange::tooLarge(\PHP_INT_MAX, 'en'));

        new NumberWords('en')->cardinal(\PHP_INT_MAX);
    }

    public function testDigitsOfAnyScriptAreRefused(): void
    {
        $this->expectException(NumberOutOfRange::class);

        new NumberWords('ar_EG')->cardinal(\PHP_INT_MAX);
    }

    public function testTheLargestNumberInWords(): void
    {
        self::assertStringStartsWith('nine hundred ninety-nine quadrillion', new NumberWords('en')->cardinal(999_999_999_999_999_999));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedLocales(): iterable
    {
        yield 'not a locale' => ['zz'];
        yield 'a language ICU does not spell' => ['tlh'];
        yield 'empty' => [''];
    }

    #[DataProvider('unsupportedLocales')]
    public function testALocaleICUDoesNotSpellIsRefused(string $locale): void
    {
        $this->expectExceptionObject(UnsupportedLocale::noRules($locale));

        new NumberWords($locale);
    }

    public function testRulesOfAnotherLanguageAreRefused(): void
    {
        $this->expectExceptionObject(UnsupportedLocale::otherLanguage('en', 'fr'));

        new NumberWords('en', rules: new French());
    }

    public function testAmountsNeedRulesForTheLanguage(): void
    {
        $this->expectExceptionObject(UnsupportedLocale::noAmounts('nl'));

        new NumberWords('nl')->amount('1', 'EUR');
    }

    public function testRulesPassedForAnotherLanguageGiveItsAmounts(): void
    {
        $dutch = new class extends AbstractLanguageRules {
            public function language(): string
            {
                return 'nl';
            }

            protected function currencies(): array
            {
                return ['EUR' => ['euro', 'euro', Gender::Masculine, 2, 'cent', 'cent', Gender::Masculine]];
            }

            protected function connector(): string
            {
                return 'en';
            }

            protected function minus(): string
            {
                return 'min';
            }
        };

        self::assertSame('min eenentwintig euro en vijf cent', new NumberWords('nl', rules: $dutch)->amount('-21.05', 'EUR'));
        self::assertSame('een miljoen euro', new NumberWords('nl', rules: $dutch)->amount('1000000', 'EUR'));
    }

    /**
     * @return iterable<string, array{string, int|string, string, string}>
     */
    public static function amounts(): iterable
    {
        yield 'English' => ['en', '120.05', 'USD', 'one hundred twenty dollars and five cents'];
        yield 'English, one of each' => ['en', '1.01', 'GBP', 'one pound and one penny'];
        yield 'English, zero takes the plural' => ['en', 0, 'EUR', 'zero euros'];
        yield 'English, no partitive' => ['en', '1000000', 'EUR', 'one million euros'];
        yield 'English, a code in lowercase' => ['en', 2, 'cad', 'two dollars'];
        yield 'English, a whole number as an int' => ['en', -3, 'AUD', 'minus three dollars'];
        yield 'English, a currency without decimals' => ['en', '5.00', 'JPY', 'five yen'];
        yield 'English, trailing zeros past the precision' => ['en', '1.500', 'NZD', 'one dollar and fifty cents'];
        yield 'English, one decimal' => ['en', '0.5', 'CHF', 'fifty centimes'];
        yield 'English, a negative zero' => ['en', '-0.00', 'USD', 'zero dollars'];
        yield 'English, a negative sub-unit' => ['en', '-0.05', 'USD', 'minus five cents'];
        yield 'English, Mexican pesos' => ['en', '2.02', 'MXN', 'two pesos and two centavos'];
        yield 'English, reais' => ['en', '2', 'BRL', 'two reais'];
        yield 'Spanish' => ['es', '120.05', 'USD', 'ciento veinte dólares con cinco centavos'];
        yield 'Spanish, before a masculine noun' => ['es', '21', 'EUR', 'veintiún euros'];
        yield 'Spanish, before a feminine noun' => ['es', '21.01', 'GBP', 'veintiuna libras con un penique'];
        yield 'Spanish, one million' => ['es', '1000000', 'EUR', 'un millón de euros'];
        yield 'Spanish, billions' => ['es', '2000000000000', 'MXN', 'dos billones de pesos'];
        yield 'Spanish, a thousand millions' => ['es', '-2000000000', 'EUR', 'menos dos mil millones de euros'];
        yield 'German' => ['de', '120.05', 'USD', 'einhundertzwanzig Dollar und fünf Cent'];
        yield 'German, a neuter unit' => ['de', '1.01', 'GBP', 'ein Pfund und ein Penny'];
        yield 'German, no partitive' => ['de', '1000000', 'EUR', 'eine Million Euro'];
        yield 'German, minus' => ['de', '-2', 'CHF', 'minus zwei Franken'];
        yield 'Italian' => ['it', '120.05', 'USD', 'centoventi dollari e cinque centesimi'];
        yield 'Italian, one' => ['it', '1', 'EUR', 'un euro'];
        yield 'Italian, one million' => ['it', '1000000', 'EUR', 'un milione di euro'];
        yield 'Italian, billions' => ['it', '-2000000000', 'EUR', 'meno due miliardi di euro'];
        yield 'Portuguese' => ['pt', '120.05', 'USD', 'cento e vinte dólares e cinco centavos'];
        yield 'Portuguese, a feminine unit' => ['pt', '2', 'GBP', 'duas libras'];
        yield 'Portuguese, one million' => ['pt', '1000000', 'BRL', 'um milhão de reais'];
        yield 'Brazilian Portuguese, billions' => ['pt', '-2000000000', 'EUR', 'menos dois bilhões de euros'];
        yield 'European Portuguese, a thousand millions' => ['pt_PT', '2000000000.01', 'EUR', 'dois mil milhões de euros e um cêntimo'];
    }

    #[DataProvider('amounts')]
    public function testAmounts(string $locale, int|string $amount, string $currency, string $expected): void
    {
        self::assertSame($expected, new NumberWords($locale)->amount($amount, $currency));
    }

    public function testAnAmountInAGivenCurrency(): void
    {
        $crown = new CurrencyName('krone', 'kroner', fractionDigits: 2, subunitOne: 'øre');

        self::assertSame('two kroner and five øre', new NumberWords('en')->amount('2.05', $crown));
        self::assertSame('one krone', new NumberWords('en')->amount('1', $crown));
    }

    public function testACurrencyTheRulesDoNotNameIsRefused(): void
    {
        $this->expectExceptionObject(MissingRule::currency('en', 'xts'));

        new NumberWords('en')->amount('1', 'xts');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDecimals(): iterable
    {
        yield 'letters before' => ['x12'];
        yield 'letters after' => ['12x'];
        yield 'a new line after' => ["12\n"];
        yield 'a point without decimals' => ['12.'];
        yield 'a comma' => ['12,50'];
        yield 'an exponent' => ['1e3'];
        yield 'a plus sign' => ['+1'];
        yield 'nineteen digits' => ['1234567890123456789'];
        yield 'empty' => [''];
    }

    #[DataProvider('notDecimals')]
    public function testAnAmountThatIsNotDecimalIsRefused(string $amount): void
    {
        $this->expectExceptionObject(InvalidAmount::notDecimal($amount));

        new NumberWords('en')->amount($amount, 'USD');
    }

    public function testTheSmallestIntIsRefused(): void
    {
        $this->expectExceptionObject(InvalidAmount::notDecimal((string) \PHP_INT_MIN));

        new NumberWords('en')->amount(\PHP_INT_MIN, 'USD');
    }

    public function testMoreDecimalsThanTheCurrencyAreRefused(): void
    {
        $this->expectExceptionObject(InvalidAmount::tooPrecise('1.005', 2));

        new NumberWords('en')->amount('1.005', 'USD');
    }

    public function testDecimalsOfACurrencyWithoutAreRefused(): void
    {
        $this->expectExceptionObject(InvalidAmount::tooPrecise('5.1', 0));

        new NumberWords('en')->amount('5.1', 'JPY');
    }

    public function testExceptionMessages(): void
    {
        self::assertSame('ICU has no rules to spell numbers in "zz".', UnsupportedLocale::noRules('zz')->getMessage());
        self::assertSame('The rules passed are for "fr", not for "en".', UnsupportedLocale::otherLanguage('en', 'fr')->getMessage());
        self::assertSame('Amounts in words have no rules for "nl": pass a LanguageRules that has them.', UnsupportedLocale::noAmounts('nl')->getMessage());
        self::assertSame('ICU has no rule set "%x" for "fr".', MissingRule::ruleSet('fr', '%x')->getMessage());
        self::assertSame('No names for the currency "XTS" in "en": pass a CurrencyName.', MissingRule::currency('en', 'XTS')->getMessage());
        self::assertSame('The currency "krone" has decimals but no name for its sub-unit.', MissingRule::subunit('krone')->getMessage());
        self::assertSame('The number 7 is too large to spell in words in "en".', NumberOutOfRange::tooLarge(7, 'en')->getMessage());
        self::assertSame('An ordinal is zero or more; -1 is not.', NumberOutOfRange::negativeOrdinal(-1)->getMessage());
        self::assertSame('A currency has 0 to 4 decimals; 5 is not supported.', NumberOutOfRange::fractionDigits(5)->getMessage());
        self::assertSame('"x" is not a decimal amount such as "120.05" or "-3".', InvalidAmount::notDecimal('x')->getMessage());
        self::assertSame('"1.005" has more than 2 decimals, the precision of its currency; round it first.', InvalidAmount::tooPrecise('1.005', 2)->getMessage());
    }
}
