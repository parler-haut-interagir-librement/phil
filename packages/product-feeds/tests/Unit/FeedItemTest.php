<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductFeeds\AgeGroup;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\Condition;
use Phil\ProductFeeds\Exception\InvalidFeedItem;
use Phil\ProductFeeds\Exception\ProductFeedException;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\Gender;
use Phil\ProductFeeds\Gtin;
use Phil\ProductFeeds\SalePeriod;
use Phil\ProductFeeds\Shipping;
use Phil\ProductFeeds\Text;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedItem::class)]
#[CoversClass(Text::class)]
#[CoversClass(Gtin::class)]
#[CoversClass(InvalidFeedItem::class)]
#[CoversClass(Availability::class)]
final class FeedItemTest extends TestCase
{
    public function testAnItemNeedsAnIdentifierATitleALinkAPriceAndAnAvailability(): void
    {
        $item = Items::item();

        self::assertSame(['DICE-1', 'Dice set', 'https://shop.example/products/DICE-1', 2_500, Availability::InStock], [$item->id, $item->title, $item->link->toString(), $item->price->minorUnits, $item->availability]);
        self::assertSame(Condition::New, $item->condition);
        self::assertNull($item->description);
        self::assertNull($item->imageLink);
        self::assertSame([], $item->additionalImageLinks);
        self::assertNull($item->availabilityDate);
        self::assertNull($item->salePrice);
        self::assertNull($item->salePeriod);
        self::assertSame([null, null, null, null, null, null, null, null, null], [$item->brand, $item->gtin, $item->mpn, $item->itemGroupId, $item->color, $item->size, $item->material, $item->pattern, $item->productType]);
        self::assertSame([null, null, null, [], null, null, false, []], [$item->googleProductCategory, $item->ageGroup, $item->gender, $item->shipping, $item->shippingWeight, $item->multipack, $item->isBundle, $item->customLabels]);
        self::assertFalse($item->hasIdentifier());
    }

    public function testEveryFieldIsKept(): void
    {
        $item = new FeedItem(
            'SHIRT-M-RED',
            'Shirt',
            Items::url('https://shop.example/shirt'),
            Items::cad(3_000),
            Availability::Preorder,
            description: "One line\nAnother",
            imageLink: Items::url('https://cdn.example/shirt.jpg'),
            additionalImageLinks: [Items::url('https://cdn.example/back.jpg')],
            availabilityDate: new DateTimeImmutable('2026-12-01 09:00:00', new DateTimeZone('America/Toronto')),
            salePrice: Items::cad(2_500),
            salePeriod: new SalePeriod(new DateTimeImmutable('2026-11-27 00:00:00+00:00'), new DateTimeImmutable('2026-12-01 00:00:00+00:00')),
            condition: Condition::Used,
            brand: 'Gnome',
            gtin: '4006381333931',
            mpn: 'SH-1',
            itemGroupId: 'SHIRT',
            color: 'Red',
            size: 'M',
            material: 'Cotton',
            pattern: 'Plain',
            productType: 'Apparel > Shirts',
            googleProductCategory: 'Apparel & Accessories > Clothing > Shirts & Tops',
            ageGroup: AgeGroup::Adult,
            gender: Gender::Unisex,
            shipping: [new Shipping('CA', Items::cad(500))],
            shippingWeight: 250,
            multipack: 3,
            isBundle: true,
            customLabels: ['Sale', ' ', 'Autumn'],
        );

        self::assertSame("One line\nAnother", $item->description);
        self::assertSame(['Apparel & Accessories > Clothing > Shirts & Tops', AgeGroup::Adult, Gender::Unisex, 250, 3, true, ['Sale', null, 'Autumn']], [$item->googleProductCategory, $item->ageGroup, $item->gender, $item->shippingWeight, $item->multipack, $item->isBundle, $item->customLabels]);
        self::assertSame('CA', $item->shipping[0]->country);
        self::assertSame('https://cdn.example/shirt.jpg', $item->imageLink?->toString());
        self::assertCount(1, $item->additionalImageLinks);
        self::assertSame('2026-12-01T14:00:00+00:00', $item->availabilityDate?->format(\DATE_ATOM));
        self::assertSame(2_500, $item->salePrice?->minorUnits);
        self::assertSame(Condition::Used, $item->condition);
        self::assertSame(['Gnome', '4006381333931', 'SH-1', 'SHIRT', 'Red', 'M', 'Cotton', 'Plain', 'Apparel > Shirts'], [$item->brand, $item->gtin, $item->mpn, $item->itemGroupId, $item->color, $item->size, $item->material, $item->pattern, $item->productType]);
        self::assertTrue($item->hasIdentifier());
    }

    public function testOnlyTheDescriptionKeepsItsLineBreaks(): void
    {
        $item = new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, description: "Soft  \nwarm", brand: "Gnome\n  Games", mpn: "M\tP\nN");

        self::assertSame(["Soft\nwarm", 'Gnome Games', 'M P N'], [$item->description, $item->brand, $item->mpn]);
    }

    public function testAGtinOrAnMpnAloneIdentifiesTheItem(): void
    {
        $gtin = new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, gtin: '96385074');
        $mpn = new FeedItem('B', 'B', Items::url('https://shop.example/b'), Items::cad(1), Availability::InStock, mpn: 'M-1');
        $brand = new FeedItem('C', 'C', Items::url('https://shop.example/c'), Items::cad(1), Availability::InStock, brand: 'Gnome');

        self::assertSame([true, true, false], [$gtin->hasIdentifier(), $mpn->hasIdentifier(), $brand->hasIdentifier()]);
    }

    public function testTextIsCleanedAndTrimmed(): void
    {
        $item = new FeedItem(
            'A',
            "  Dice\tset\n\nof  six\x07 ",
            Items::url('https://shop.example/a'),
            Items::cad(1),
            Availability::InStock,
            description: " First line  \r\nSecond\x00 line \t\nThird\u{FFFE}\x7F ",
            brand: "\x0B",
            color: " Deep \u{FFFF}red ",
        );

        self::assertSame('Dice set of six', $item->title);
        self::assertSame("First line\nSecond line\nThird", $item->description);
        self::assertNull($item->brand);
        self::assertSame('Deep red', $item->color);
    }

    public function testTextThatIsNotUtf8IsRefused(): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The title of A is not UTF-8.');

        new FeedItem('A', "Dice \xC3", Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock);
    }

    public function testABlankTitleIsRefused(): void
    {
        $this->expectException(ProductFeedException::class);
        $this->expectExceptionMessageIs('The title of A holds 1 to 150 characters, not blank.');

        new FeedItem('A', " \x01\n ", Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function lengths(): iterable
    {
        yield 'title' => ['title', FeedItem::MAX_TITLE, 'The title of A holds 150 characters at most.'];
        yield 'description' => ['description', FeedItem::MAX_DESCRIPTION, 'The description of A holds 5000 characters at most.'];
        yield 'brand' => ['brand', FeedItem::MAX_BRAND, 'The brand of A holds 70 characters at most.'];
        yield 'mpn' => ['mpn', FeedItem::MAX_MPN, 'The MPN of A holds 70 characters at most.'];
        yield 'color' => ['color', FeedItem::MAX_VARIANT_VALUE, 'The colour of A holds 100 characters at most.'];
        yield 'size' => ['size', FeedItem::MAX_VARIANT_VALUE, 'The size of A holds 100 characters at most.'];
        yield 'material' => ['material', FeedItem::MAX_MATERIAL, 'The material of A holds 200 characters at most.'];
        yield 'pattern' => ['pattern', FeedItem::MAX_VARIANT_VALUE, 'The pattern of A holds 100 characters at most.'];
        yield 'product type' => ['productType', FeedItem::MAX_PRODUCT_TYPE, 'The product type of A holds 750 characters at most.'];
        yield 'product category' => ['googleProductCategory', FeedItem::MAX_PRODUCT_TYPE, 'The product category of A holds 750 characters at most.'];
        yield 'custom label' => ['customLabel', FeedItem::MAX_CUSTOM_LABEL, 'The custom label of A holds 100 characters at most.'];
    }

    #[DataProvider('lengths')]
    public function testEachTextFieldHoldsItsMaximumInCharacters(string $field, int $maximum, string $message): void
    {
        $item = $this->withText($field, str_repeat('é', $maximum));
        self::assertSame(str_repeat('é', $maximum), match ($field) {
            'title' => $item->title,
            'description' => $item->description,
            'brand' => $item->brand,
            'mpn' => $item->mpn,
            'color' => $item->color,
            'size' => $item->size,
            'material' => $item->material,
            'pattern' => $item->pattern,
            'googleProductCategory' => $item->googleProductCategory,
            'customLabel' => $item->customLabels[0],
            default => $item->productType,
        });

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs($message);

        $this->withText($field, str_repeat('é', $maximum + 1));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function identifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'a space' => ['DICE 1'];
        yield 'beyond ASCII' => ['DÉ'];
        yield 'a line break' => ["DICE\n"];
        yield 'too long' => [str_repeat('A', 51)];
    }

    #[DataProvider('identifiers')]
    public function testAnIdentifierIsVisibleAscii(string $id): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The identifier of an item holds 1 to 50 visible ASCII characters.');

        new FeedItem($id, 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock);
    }

    public function testAnIdentifierHoldsFiftyCharacters(): void
    {
        self::assertSame(str_repeat('A', 50), Items::item(str_repeat('A', 50))->id);
        self::assertSame('!~', Items::item('!~')->id);
    }

    public function testAGroupIsAnIdentifierToo(): void
    {
        self::assertSame(str_repeat('G', 50), new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, itemGroupId: str_repeat('G', 50))->itemGroupId);

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The group of A holds 1 to 50 visible ASCII characters.');

        new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, itemGroupId: str_repeat('G', 51));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function gtins(): iterable
    {
        yield 'EAN-8' => ['96385074'];
        yield 'UPC-A' => ['036000291452'];
        yield 'EAN-13' => ['4006381333931'];
        yield 'GTIN-14' => ['10614141000415'];
        yield 'leading zeros' => ['00000000000017'];
        yield 'a check digit of zero' => ['00000710'];
    }

    #[DataProvider('gtins')]
    public function testAGtinIsCheckedByItsLastDigit(string $gtin): void
    {
        self::assertSame($gtin, new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, gtin: $gtin)->gtin);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notGtins(): iterable
    {
        yield 'a wrong check digit' => ['4006381333932'];
        yield 'another wrong check digit' => ['036000291453'];
        yield 'a wrong first digit' => ['5006381333931'];
        yield 'seven digits' => ['1234567'];
        yield 'nine digits' => ['123456789'];
        yield 'eleven digits' => ['12345678901'];
        yield 'fifteen digits' => ['106141410004150'];
        yield 'a letter' => ['4006381333A31'];
        yield 'a trailing line break' => ["96385074\n"];
        yield 'a line break checking out' => ["400638133390\n"];
        yield 'a letter before' => ['x4006381333931'];
        yield 'a letter after' => ['400638133390x'];
        yield 'eight zeros' => ['00000000'];
        yield 'fourteen zeros' => ['00000000000000'];
    }

    #[DataProvider('notGtins')]
    public function testAnythingElseIsNoGtin(string $gtin): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs(\sprintf('"%s" is not a GTIN: 8, 12, 13 or 14 digits, not all zeros, the last one checking the others.', $gtin));

        new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, gtin: $gtin);
    }

    public function testTenAdditionalImagesAtMost(): void
    {
        $images = array_map(static fn (int $i): Url => Items::url('https://cdn.example/'.$i.'.jpg'), range(1, 10));
        self::assertCount(10, new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, additionalImageLinks: $images)->additionalImageLinks);

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The item A has 10 additional images at most.');

        new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, additionalImageLinks: [...$images, Items::url('https://cdn.example/11.jpg')]);
    }

    public function testAFreeItemIsPriced(): void
    {
        self::assertTrue(new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(0), Availability::InStock)->price->isZero());
    }

    /**
     * @return iterable<string, array{Money, ?Money, ?SalePeriod, string}>
     */
    public static function prices(): iterable
    {
        $period = new SalePeriod(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-02'));

        yield 'a negative price' => [Items::cad(-1), null, null, 'The item A has a negative price.'];
        yield 'a period without a sale price' => [Items::cad(100), null, $period, 'The item A has a sale period but no sale price.'];
        yield 'a sale price in another currency' => [Items::cad(100), Money::of(50, Currency::of('USD')), null, 'The sale price of A is in USD, its price in CAD.'];
        yield 'a sale price equal to the price' => [Items::cad(100), Items::cad(100), null, 'The sale price of A is below its price, and not negative.'];
        yield 'a sale price above the price' => [Items::cad(100), Items::cad(101), null, 'The sale price of A is below its price, and not negative.'];
        yield 'a negative sale price' => [Items::cad(100), Items::cad(-1), null, 'The sale price of A is below its price, and not negative.'];
    }

    #[DataProvider('prices')]
    public function testASalePriceIsBelowThePriceInItsCurrency(Money $price, ?Money $salePrice, ?SalePeriod $period, string $message): void
    {
        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs($message);

        new FeedItem('A', 'A', Items::url('https://shop.example/a'), $price, Availability::InStock, salePrice: $salePrice, salePeriod: $period);
    }

    public function testASalePriceMayBeZeroOrComeWithoutAPeriod(): void
    {
        $item = new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(100), Availability::InStock, salePrice: Items::cad(0));

        self::assertTrue($item->salePrice?->isZero());
        self::assertNull($item->salePeriod);
        self::assertSame(99, new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(100), Availability::InStock, salePrice: Items::cad(99))->salePrice?->minorUnits);
    }

    public function testOnlyAPreorderOrABackorderHasAnAvailabilityDate(): void
    {
        $date = new DateTimeImmutable('2026-12-01');

        self::assertSame([false, false, true, true], array_map(static fn (Availability $availability): bool => $availability->takesADate(), Availability::cases()));
        self::assertNotNull(new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::Backorder, availabilityDate: $date)->availabilityDate);

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The item A is out_of_stock: only a preorder or a backorder has an availability date.');

        new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::OutOfStock, availabilityDate: $date);
    }

    public function testAHundredShippingCostsAtMost(): void
    {
        $shipping = array_map(static fn (int $i): Shipping => new Shipping('CA', Items::cad($i)), range(1, FeedItem::MAX_SHIPPING));
        self::assertCount(100, $this->withShipping($shipping)->shipping);

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The item A has 100 shipping costs at most.');

        $this->withShipping([...$shipping, new Shipping('US', Items::cad(1))]);
    }

    /**
     * @return iterable<string, array{?int, ?int, ?string}>
     */
    public static function quantities(): iterable
    {
        yield 'the lightest weight' => [1, null, null];
        yield 'the heaviest weight' => [1_000_000, null, null];
        yield 'no weight' => [0, null, 'The shipping weight of A is 1 to 1000000 grams.'];
        yield 'too heavy' => [1_000_001, null, 'The shipping weight of A is 1 to 1000000 grams.'];
        yield 'the smallest multipack' => [null, 2, null];
        yield 'the largest multipack' => [null, 10_000, null];
        yield 'a multipack of one' => [null, 1, 'A multipack of A holds 2 to 10000 units.'];
        yield 'too large a multipack' => [null, 10_001, 'A multipack of A holds 2 to 10000 units.'];
    }

    #[DataProvider('quantities')]
    public function testTheShippingWeightAndTheMultipackAreBounded(?int $weight, ?int $multipack, ?string $message): void
    {
        if (null !== $message) {
            $this->expectException(InvalidFeedItem::class);
            $this->expectExceptionMessageIs($message);
        }

        $item = new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, shippingWeight: $weight, multipack: $multipack);

        self::assertSame([$weight, $multipack], [$item->shippingWeight, $item->multipack]);
    }

    public function testFiveCustomLabelsAtMost(): void
    {
        $labels = ['0', '1', '2', '3', '4'];
        self::assertSame($labels, new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, customLabels: $labels)->customLabels);

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The item A has 5 custom labels at most.');

        new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, customLabels: [...$labels, '5']);
    }

    /**
     * @param list<Shipping> $shipping
     */
    private function withShipping(array $shipping): FeedItem
    {
        return new FeedItem('A', 'A', Items::url('https://shop.example/a'), Items::cad(1), Availability::InStock, shipping: $shipping);
    }

    private function withText(string $field, string $text): FeedItem
    {
        $link = Items::url('https://shop.example/a');

        return match ($field) {
            'title' => new FeedItem('A', $text, $link, Items::cad(1), Availability::InStock),
            'description' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, description: $text),
            'brand' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, brand: $text),
            'mpn' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, mpn: $text),
            'color' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, color: $text),
            'size' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, size: $text),
            'material' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, material: $text),
            'pattern' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, pattern: $text),
            'googleProductCategory' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, googleProductCategory: $text),
            'customLabel' => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, customLabels: [$text]),
            default => new FeedItem('A', 'A', $link, Items::cad(1), Availability::InStock, productType: $text),
        };
    }
}
