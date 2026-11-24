<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Domain;

use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The price rules and the serial numbers of a product.
 */
#[CoversClass(Product::class)]
final class PriceRulesTest extends TestCase
{
    public function testAProductIsSoldAtItsPriceUntilTheBuyerChooses(): void
    {
        $zine = $this->product();

        self::assertFalse($zine->isPayWhatYouWant());
        self::assertNull($zine->chosenPrices($this->cad(5_000)));
        self::assertNull($zine->suggestedPrice());
        self::assertNull($zine->maximumPrice());
    }

    public function testTheBuyerChoosesFromTheBasePriceUpToTheMaximum(): void
    {
        $zine = $this->product();
        $zine->payWhatYouWant($this->cad(800), $this->cad(5_000));

        self::assertTrue($zine->isPayWhatYouWant());
        self::assertSame(800, $zine->suggestedPrice()?->minorUnits);
        self::assertSame('CAD', $zine->suggestedPrice()->currency->code);
        self::assertSame(5_000, $zine->maximumPrice()?->minorUnits);
        $range = $zine->chosenPrices($this->cad(9_000));
        self::assertSame([500, 5_000], [$range?->minimum->minorUnits, $range?->maximum->minorUnits]);
        self::assertSame('CAD', $range?->minimum->currency->code);

        $zine->payWhatYouWant();
        self::assertNull($zine->chosenPrices());
        self::assertSame(9_000, $zine->chosenPrices($this->cad(9_000))?->maximum->minorUnits);
        self::assertSame(500, $zine->chosenPrices($this->cad(500))?->maximum->minorUnits);
        self::assertNull($zine->chosenPrices($this->cad(499)));
        self::assertNull($zine->chosenPrices(Money::of(9_000, Currency::of('EUR'))));
        self::assertNull($zine->suggestedPrice());

        $zine->payWhatYouWant($this->cad(800));
        self::assertSame(800, $zine->chosenPrices($this->cad(800))?->maximum->minorUnits);
        self::assertNull($zine->chosenPrices($this->cad(799)));

        $zine->fixPrice();
        self::assertFalse($zine->isPayWhatYouWant());
        self::assertNull($zine->maximumPrice());
        self::assertNull($zine->chosenPrices($this->cad(9_000)));
    }

    public function testTheSuggestedPriceMayEqualTheBasePriceAndTheMaximum(): void
    {
        $zine = $this->product();
        $zine->payWhatYouWant($this->cad(500), $this->cad(500));

        self::assertSame([500, 500], [$zine->chosenPrices()?->minimum->minorUnits, $zine->chosenPrices()?->maximum->minorUnits]);
    }

    public function testASuggestedPriceBelowTheBasePriceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product ZINE is paid from its base price up: the suggested price, then the maximum, are not below it.');

        $this->product()->payWhatYouWant($this->cad(499));
    }

    public function testAMaximumBelowTheSuggestedPriceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        $this->product()->payWhatYouWant($this->cad(900), $this->cad(800));
    }

    public function testAMaximumBelowTheBasePriceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        $this->product()->payWhatYouWant(null, $this->cad(499));
    }

    public function testAPriceRuleCountsInTheProductsCurrency(): void
    {
        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIsOrContains('The product ZINE counts in CAD; EUR is refused.');

        $this->product()->payWhatYouWant(null, Money::of(900, Currency::of('EUR')));
    }

    public function testASuggestedPriceCountsInTheProductsCurrency(): void
    {
        $this->expectException(CatalogRefused::class);

        $this->product()->payWhatYouWant(Money::of(900, Currency::of('EUR')));
    }

    public function testTheBasePriceStaysBelowTheSuggestedPrice(): void
    {
        $zine = $this->product();
        $zine->payWhatYouWant($this->cad(800), $this->cad(900));
        $zine->reprice($this->cad(800));
        self::assertSame(800, $zine->price->minorUnits);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product ZINE is paid from its base price up: its base price stays below its suggested price and its maximum.');

        $zine->reprice($this->cad(801));
    }

    public function testTheBasePriceStaysBelowTheMaximum(): void
    {
        $zine = $this->product();
        $zine->payWhatYouWant(null, $this->cad(900));
        $zine->reprice($this->cad(900));

        $this->expectException(InvalidValue::class);

        $zine->reprice($this->cad(901));
    }

    public function testCommunityCopiesAreOfferedAtAPrice(): void
    {
        $zine = $this->product();
        self::assertNull($zine->communityCopyPrice());

        $zine->offerCommunityCopies($this->cad(1));
        self::assertSame(1, $zine->communityCopyPrice()?->minorUnits);
        self::assertSame('CAD', $zine->communityCopyPrice()->currency->code);

        $zine->offerCommunityCopies(null);
        self::assertNull($zine->communityCopyPrice());
    }

    public function testACommunityCopyCostsMoreThanZero(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A community copy of ZINE costs more than zero.');

        $this->product()->offerCommunityCopies($this->cad(0));
    }

    public function testACommunityCopyCountsInTheProductsCurrency(): void
    {
        $this->expectException(CatalogRefused::class);

        $this->product()->offerCommunityCopies(Money::of(100, Currency::of('EUR')));
    }

    public function testTheSuggestedRetailPriceIsKeptBesideTheBasePrice(): void
    {
        $zine = $this->product();
        self::assertNull($zine->suggestedRetailPrice());

        $zine->suggestRetailPrice($this->cad(1));
        self::assertSame([1, 'CAD'], [$zine->suggestedRetailPrice()?->minorUnits, $zine->suggestedRetailPrice()?->currency->code]);
        self::assertSame(500, $zine->price->minorUnits);

        $zine->suggestRetailPrice($this->cad(1_299));
        self::assertSame(1_299, $zine->suggestedRetailPrice()?->minorUnits);

        $zine->suggestRetailPrice(null);
        self::assertNull($zine->suggestedRetailPrice());
    }

    public function testASuggestedRetailPriceIsMoreThanZero(): void
    {
        $zine = $this->product();
        $zine->suggestRetailPrice($this->cad(1_299));

        try {
            $zine->suggestRetailPrice($this->cad(0));
            self::fail('A suggested retail price of zero was kept.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('The suggested retail price of ZINE is more than zero.', $invalidValue->getMessage());
        }

        self::assertSame(1_299, $zine->suggestedRetailPrice()?->minorUnits);
        $this->expectException(InvalidValue::class);
        $zine->suggestRetailPrice($this->cad(-1));
    }

    public function testASuggestedRetailPriceCountsInTheProductsCurrency(): void
    {
        $this->expectExceptionObject(CatalogRefused::currency('The product ZINE', 'CAD', 'EUR'));

        $this->product()->suggestRetailPrice(Money::of(100, Currency::of('EUR')));
    }

    public function testAPhysicalProductCarriesSerialNumbersAndItsVariantsToo(): void
    {
        $console = Product::create('CONSOLE', ProductKind::Physical, 'Console', $this->cad(30_000), 'goods');
        self::assertFalse($console->tracksSerialNumbers());

        $console->trackSerialNumbers();
        $console->defineAxes(['colour']);
        self::assertTrue($console->tracksSerialNumbers());
        self::assertTrue(Product::variant($console, 'CONSOLE-W', ['colour' => 'white'])->tracksSerialNumbers());

        $console->trackSerialNumbers(false);
        self::assertFalse($console->tracksSerialNumbers());
        self::assertFalse(Product::variant($console, 'CONSOLE-B', ['colour' => 'black'])->tracksSerialNumbers());
    }

    public function testADigitalProductCarriesNoSerialNumbers(): void
    {
        $this->product()->trackSerialNumbers(false);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The product ZINE is not physical: it carries no serial numbers.');

        $this->product()->trackSerialNumbers();
    }

    private function product(): Product
    {
        return Product::create('ZINE', ProductKind::Digital, 'Zine', $this->cad(500), 'books');
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
