<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\Money\Currency;
use Phil\Money\Exception\UnknownCurrency;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Currencies;

#[CoversClass(AcceptedCurrencies::class)]
final class AcceptedCurrenciesTest extends TestCase
{
    public function testAListAcceptsItsCurrenciesInOrder(): void
    {
        $accepted = AcceptedCurrencies::of(['USD', 'USD', 'CAD']);

        self::assertTrue($accepted->isRestricted());
        self::assertSame(['USD', 'CAD'], $this->codes($accepted->all()));
        self::assertTrue($accepted->accepts(Currency::of('CAD')));
        self::assertFalse($accepted->accepts(Currency::of('EUR')));
        self::assertSame([0, 1], array_keys($accepted->all()));
    }

    public function testAListDefaultsToItsFirstCurrency(): void
    {
        self::assertSame('USD', AcceptedCurrencies::of(['USD', 'CAD'])->default()?->code);
        self::assertSame('CAD', AcceptedCurrencies::of(['USD', 'CAD'], 'CAD')->default()?->code);
    }

    public function testADefaultOutsideTheListIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('The default currency EUR is not one of the accepted currencies.'));

        AcceptedCurrencies::of(['USD', 'CAD'], 'EUR');
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $this->expectException(UnknownCurrency::class);

        AcceptedCurrencies::of(['XYZ']);
    }

    public function testACodeIsKnownToTheCurrencyData(): void
    {
        self::assertTrue(AcceptedCurrencies::isKnown('CAD'));
        self::assertFalse(AcceptedCurrencies::isKnown('XYZ'));
    }

    public function testAnEmptyListAcceptsEveryKnownCurrency(): void
    {
        $accepted = AcceptedCurrencies::of([]);

        self::assertFalse($accepted->isRestricted());
        self::assertTrue($accepted->accepts(Currency::of('EUR')));
        self::assertTrue($accepted->accepts(Currency::of('DEM')));
        self::assertNull($accepted->default());
        self::assertSame('EUR', AcceptedCurrencies::of([], 'EUR')->default()?->code);
    }

    public function testAnEmptyListOffersOnlyTheCurrenciesInCurrentUse(): void
    {
        $accepted = AcceptedCurrencies::of([]);
        $codes = $this->codes($accepted->all());

        self::assertContains('CAD', $codes);
        self::assertContains('EUR', $codes);
        self::assertNotContains('DEM', $codes);
        self::assertSame(array_values(array_filter(Currencies::getCurrencyCodes(), Currencies::isValidInAnyCountry(...))), $codes);
        self::assertSame($accepted->all(), $accepted->all());
    }

    /**
     * @param list<Currency> $currencies
     *
     * @return list<string>
     */
    private function codes(array $currencies): array
    {
        return array_map(static fn (Currency $currency): string => $currency->code, $currencies);
    }
}
