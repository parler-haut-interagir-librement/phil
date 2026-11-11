<?php

declare(strict_types=1);

namespace Phil\Money\Tests\Unit;

use Locale;
use Override;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Exception\UnknownCurrency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Currency::class)]
#[CoversClass(UnknownCurrency::class)]
final class CurrencyTest extends TestCase
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

    public function testAKnownCodeIsACurrency(): void
    {
        $currency = Currency::of('CAD');

        self::assertSame('CAD', $currency->code);
        self::assertSame('CAD', (string) $currency);
    }

    public function testACodeIsOneInstance(): void
    {
        self::assertSame(Currency::of('EUR'), Currency::of('EUR'));
        self::assertNotSame(Currency::of('EUR'), Currency::of('USD'));
    }

    public function testARefusedCodeStaysRefused(): void
    {
        $refusals = [];

        foreach ([1, 2] as $attempt) {
            try {
                Currency::of('XYZ');
            } catch (UnknownCurrency $unknownCurrency) {
                $refusals[] = $attempt.' '.$unknownCurrency->getMessage();
            }
        }

        self::assertSame(['1 "XYZ" is not a known ISO 4217 currency code.', '2 "XYZ" is not a known ISO 4217 currency code.'], $refusals);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedCodes(): iterable
    {
        yield 'well formed, unknown' => ['XYZ'];
        yield 'lowercase' => ['cad'];
        yield 'two letters' => ['CA'];
        yield 'four letters' => ['CADD'];
        yield 'a trailing newline' => ["CAD\n"];
        yield 'a leading letter' => ['XCAD'];
        yield 'empty' => [''];
    }

    #[DataProvider('refusedCodes')]
    public function testAnythingElseIsRefused(string $code): void
    {
        try {
            Currency::of($code);
            self::fail('The code must be refused.');
        } catch (UnknownCurrency $unknownCurrency) {
            self::assertInstanceOf(MoneyException::class, $unknownCurrency);
            self::assertSame(\sprintf('"%s" is not a known ISO 4217 currency code.', $code), $unknownCurrency->getMessage());
        }
    }

    public function testTheFractionDigitsComeFromTheCurrencyData(): void
    {
        self::assertSame(2, Currency::of('CAD')->fractionDigits());
        self::assertSame(0, Currency::of('JPY')->fractionDigits());
        self::assertSame(3, Currency::of('BHD')->fractionDigits());
    }

    public function testNameAndSymbolAreLocalized(): void
    {
        $cad = Currency::of('CAD');

        self::assertSame('dollar canadien', $cad->name('fr'));
        self::assertSame('Canadian Dollar', $cad->name('en'));
        self::assertSame('$', $cad->symbol('fr_CA'));
        self::assertSame('CA$', $cad->symbol('en'));
    }

    public function testNameAndSymbolFollowTheDefaultLocale(): void
    {
        Locale::setDefault('fr_CA');
        $cad = Currency::of('CAD');

        self::assertSame('dollar canadien', $cad->name());
        self::assertSame('$', $cad->symbol());
    }

    public function testEquality(): void
    {
        self::assertTrue(Currency::of('CAD')->equals(Currency::of('CAD')));
        self::assertFalse(Currency::of('CAD')->equals(Currency::of('USD')));
    }
}
