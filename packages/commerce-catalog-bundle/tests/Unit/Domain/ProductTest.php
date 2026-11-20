<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Domain;

use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Product::class)]
#[CoversClass(CatalogRefused::class)]
final class ProductTest extends TestCase
{
    public function testAProductHoldsWhatItSells(): void
    {
        $product = Product::create('DICE-D20.red_1', ProductKind::Physical, 'Dé à 20 faces', Money::of(499, Currency::of('CAD')), 'goods');

        self::assertSame('DICE-D20.red_1', $product->sku);
        self::assertSame(ProductKind::Physical, $product->kind);
        self::assertSame('Dé à 20 faces', $product->name());
        self::assertSame(499, $product->price->minorUnits);
        self::assertSame('CAD', $product->price->currency->code);
        self::assertSame('goods', $product->taxCategory());
        self::assertSame([], $product->attributes());
        self::assertTrue($product->isActive());
    }

    public function testItIsRenamedRepricedAndClassified(): void
    {
        $product = $this->product();
        $product->rename('Dice set');
        $product->reprice(Money::zero(Currency::of('CAD')));
        $product->classify('zero-rated');

        self::assertSame('Dice set', $product->name());
        self::assertTrue($product->price->isZero());
        self::assertSame('zero-rated', $product->taxCategory());
    }

    public function testAttributesAreSetSortedAndRemoved(): void
    {
        $product = $this->product();
        $product->describe('size', 'large');
        $product->describe('colour', 'red');
        $product->describe('colour', 'blue');

        self::assertSame(['colour' => 'blue', 'size' => 'large'], $product->attributes());
        self::assertSame('large', $product->attribute('size'));
        self::assertNull($product->attribute('weight'));

        $product->describe('colour', null);
        self::assertSame(['size' => 'large'], $product->attributes());

        $product->describe(str_repeat('a', 64), str_repeat('é', 255));
        self::assertSame(str_repeat('é', 255), $product->attribute(str_repeat('a', 64)));
    }

    public function testAProductIsSoldThroughItsChannels(): void
    {
        $product = $this->product();
        self::assertSame([], $product->channels());
        self::assertTrue($product->isSoldThrough('web'));
        self::assertTrue($product->isSoldThrough(null));

        $product->sellThrough(['second-shop', 'second-shop', 'web', str_repeat('c', 64)]);
        self::assertSame(['second-shop', 'web', str_repeat('c', 64)], $product->channels());
        self::assertTrue($product->isSoldThrough('web'));
        self::assertFalse($product->isSoldThrough('store'));
        self::assertFalse($product->isSoldThrough(null));

        $product->sellThrough([]);
        self::assertTrue($product->isSoldThrough('store'));

        foreach (['', 'a b', str_repeat('c', 65), "web\n"] as $channel) {
            try {
                $product->sellThrough([$channel]);
                self::fail('The channel was accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('"%s" is not a channel: 1 to 64 visible ASCII characters.', $channel), $invalidValue->getMessage());
            }
        }
    }

    public function testAnOfferKeepsEachOfItsParts(): void
    {
        $product = $this->product();

        $product->changeOffer(Offer::of(Visibility::Unlisted));
        self::assertEquals(Offer::of(Visibility::Unlisted), $product->offerThrough('web'));

        $product->changeOffer(Offer::of(Visibility::Unlisted, false, false));
        self::assertEquals(Offer::of(Visibility::Unlisted, false, false), $product->offer());

        $product->changeOffer(Offer::full());
        self::assertEquals(Offer::full(), $product->offer());
    }

    public function testAProductIsOfferedInFullUntilItsOfferChanges(): void
    {
        $product = $this->product();
        self::assertEquals(Offer::full(), $product->offer());
        self::assertEquals(Offer::full(), $product->offerThrough('web'));

        $product->changeOffer(Offer::of(Visibility::Listed, false));
        self::assertEquals(Offer::of(Visibility::Listed, false), $product->offer());
        self::assertEquals(Offer::of(Visibility::Listed, false), $product->offerThrough(null));

        $product->sellThrough(['web']);
        self::assertEquals(Offer::of(Visibility::Listed, false), $product->offerThrough('web'));
        self::assertEquals(Offer::hidden(), $product->offerThrough('store'));
        self::assertEquals(Offer::hidden(), $product->offerThrough(null));

        $product->withdraw();
        self::assertEquals(Offer::hidden(), $product->offerThrough('web'));
        self::assertEquals(Offer::of(Visibility::Listed, false), $product->offer());

        $product->restore();
        $product->changeOffer(Offer::hidden());
        self::assertEquals(Offer::hidden(), $product->offerThrough('web'));
    }

    public function testAWithdrawnProductIsRestored(): void
    {
        $product = $this->product();
        $product->withdraw();
        self::assertFalse($product->isActive());

        $product->restore();
        self::assertTrue($product->isActive());
    }

    /**
     * @return iterable<string, array{string, string, int, string, string}>
     */
    public static function invalidProducts(): iterable
    {
        yield 'empty SKU' => ['', 'Dice', 1, 'goods', '"" is not a SKU: up to 64 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'SKU with a space' => ['DICE 1', 'Dice', 1, 'goods', '"DICE 1" is not a SKU'];
        yield 'SKU starting with a dash' => ['-DICE', 'Dice', 1, 'goods', 'is not a SKU'];
        yield 'SKU too long' => [str_repeat('A', 65), 'Dice', 1, 'goods', 'is not a SKU'];
        yield 'SKU with a trailing newline' => ["DICE\n", 'Dice', 1, 'goods', 'is not a SKU'];
        yield 'blank name' => ['DICE', ' ', 1, 'goods', 'A product name holds 1 to 255 characters, not blank.'];
        yield 'name too long' => ['DICE', str_repeat('é', 256), 1, 'goods', 'A product name holds 1 to 255 characters, not blank.'];
        yield 'negative price' => ['DICE', 'Dice', -1, 'goods', 'The product DICE has a negative price.'];
        yield 'no tax category' => ['DICE', 'Dice', 1, '', 'A tax category holds 1 to 64 characters.'];
        yield 'tax category too long' => ['DICE', 'Dice', 1, str_repeat('g', 65), 'A tax category holds 1 to 64 characters.'];
    }

    #[DataProvider('invalidProducts')]
    public function testAnInvalidProductIsRefused(string $sku, string $name, int $price, string $category, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Product::create($sku, ProductKind::Digital, $name, Money::of($price, Currency::of('CAD')), $category);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $product = Product::create(str_repeat('A', 64), ProductKind::Service, str_repeat('é', 255), Money::zero(Currency::of('CAD')), str_repeat('g', 64));

        self::assertSame(64, \strlen($product->sku));
        self::assertSame(64, \strlen($product->taxCategory()));
    }

    public function testARepriceInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIsOrContains('The product DICE counts in CAD; USD is refused.');

        $this->product()->reprice(Money::of(1, Currency::of('USD')));
    }

    public function testANegativeRepriceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        $this->product()->reprice(Money::of(-1, Currency::of('CAD')));
    }

    public function testABlankRenameIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        $this->product()->rename('');
    }

    public function testAnEmptyClassificationIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        $this->product()->classify('');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidAttributes(): iterable
    {
        yield 'uppercase name' => ['Colour', 'red', '"Colour" is not an attribute name: up to 64 lowercase letters, digits or "_", starting with a letter.'];
        yield 'name starting with a digit' => ['1colour', 'red', 'is not an attribute name'];
        yield 'name too long' => [str_repeat('a', 65), 'red', 'is not an attribute name'];
        yield 'name with a trailing newline' => ["colour\n", 'red', 'is not an attribute name'];
        yield 'blank value' => ['colour', ' ', 'The attribute colour of DICE holds 1 to 255 characters, not blank.'];
        yield 'value too long' => ['colour', str_repeat('é', 256), 'The attribute colour of DICE holds 1 to 255 characters, not blank.'];
    }

    #[DataProvider('invalidAttributes')]
    public function testAnInvalidAttributeIsRefused(string $name, string $value, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->product()->describe($name, $value);
    }

    public function testARefusalIsADomainError(): void
    {
        self::assertInstanceOf(DomainError::class, CatalogRefused::currency('X', 'CAD', 'USD'));
    }

    private function product(): Product
    {
        return Product::create('DICE', ProductKind::Physical, 'Dice', Money::of(1_000, Currency::of('CAD')), 'goods');
    }
}
