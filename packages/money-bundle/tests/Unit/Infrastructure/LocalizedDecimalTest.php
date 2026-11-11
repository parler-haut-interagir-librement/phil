<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\MoneyBundle\Infrastructure\Form\LocalizedDecimal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(LocalizedDecimal::class)]
final class LocalizedDecimalTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function inputs(): iterable
    {
        yield 'French, grouped by a no-break space' => ["1\u{00A0}234,56", 'fr_CA', '1234.56'];
        yield 'French, grouped by a narrow no-break space' => ["1\u{202F}234,56", 'fr_CA', '1234.56'];
        yield 'French, grouped by a space' => ['1 234,56', 'fr_CA', '1234.56'];
        yield 'French, with a dot' => ['1234.56', 'fr_CA', '1234.56'];
        yield 'French, negative with a minus sign' => ["\u{2212}12,5", 'fr_CA', '-12.5'];
        yield 'English, grouped by commas' => ['1,234.56', 'en', '1234.56'];
        yield 'English, surrounded by spaces' => ['  12.50 ', 'en', '12.50'];
        yield 'English, surrounded by a tab and a newline' => ["\t12.50\n", 'en', '12.50'];
        yield 'English, negative' => ['-0.05', 'en', '-0.05'];
        yield 'German, grouped by dots' => ['1.234,56', 'de', '1234.56'];
        yield 'German, grouped by dots without decimals' => ['1.234', 'de', '1234'];
        yield 'Swiss German, grouped by apostrophes' => ["1'234'567.5", 'de_CH', '1234567.5'];
        yield 'Indian English, grouped by two then three' => ['12,34,567.50', 'en_IN', '1234567.50'];
        yield 'English, grouped by spaces' => ['1 234 567.50', 'en', '1234567.50'];
        yield 'English, a comma before two digits' => ['12,50', 'en', null];
        yield 'German, a dot before two digits' => ['12.50', 'de', null];
        yield 'English, groups of one digit' => ['1,2,3', 'en', null];
        yield 'English, a first group too long' => ['1234,567', 'en', null];
        yield 'English, two kinds of grouping' => ['1,234 567', 'en', null];
        yield 'English, a grouping after the decimal point' => ['1.234,56', 'en', null];
        yield 'POSIX English, ungrouped' => ['1234.5', 'en_US_POSIX', '1234.5'];
        yield 'POSIX English, a grouping it does not have' => ['1,234.5', 'en_US_POSIX', null];
        yield 'letters' => ['12 $', 'fr_CA', null];
        yield 'two decimal points' => ['1,2,3', 'fr_CA', null];
        yield 'empty' => ['', 'en', null];
        yield 'a minus sign inside' => ['12-5', 'en', null];
        yield 'a trailing newline inside' => ["12\n5", 'en', null];
        yield 'a decimal point alone' => ['12.', 'en', null];
    }

    #[DataProvider('inputs')]
    public function testAnInputIsReadInItsLocale(string $input, string $locale, ?string $decimal): void
    {
        self::assertSame($decimal, LocalizedDecimal::parse($input, $locale));
    }

    public function testTheDefaultLocaleIsRead(): void
    {
        Locale::setDefault('de');

        self::assertSame('1234.5', LocalizedDecimal::parse('1.234,5'));
        self::assertSame('-1234,50', LocalizedDecimal::format('-1234.50'));
    }

    public function testADecimalIsWrittenWithTheLocalesSeparatorAndNoGrouping(): void
    {
        self::assertSame('1234,56', LocalizedDecimal::format('1234.56', 'fr_CA'));
        self::assertSame('1234.56', LocalizedDecimal::format('1234.56', 'en'));
        self::assertSame('1200', LocalizedDecimal::format('1200', 'fr_CA'));
    }

    public function testTheSymbolsOfSixtyFourLocalesAreKeptThenReadAgain(): void
    {
        $symbols = new ReflectionProperty(LocalizedDecimal::class, 'symbols');
        $symbols->setValue(null, []);

        $locales = array_map(static fn (int $number): string => 'en_'.$number, range(1, 64));

        foreach ($locales as $locale) {
            LocalizedDecimal::format('1.5', $locale);
        }

        self::assertSame($locales, array_keys((array) $symbols->getValue()));

        self::assertSame('1.5', LocalizedDecimal::format('1.5', 'en_1'));
        self::assertCount(64, (array) $symbols->getValue());

        self::assertSame('1,5', LocalizedDecimal::format('1.5', 'fr_CA'));
        self::assertSame(['fr_CA'], array_keys((array) $symbols->getValue()));
    }
}
