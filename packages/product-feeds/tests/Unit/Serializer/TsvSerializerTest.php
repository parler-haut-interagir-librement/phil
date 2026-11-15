<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit\Serializer;

use DateTimeImmutable;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductFeeds\AgeGroup;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\Gender;
use Phil\ProductFeeds\SalePeriod;
use Phil\ProductFeeds\Serializer\Fields;
use Phil\ProductFeeds\Serializer\TsvSerializer;
use Phil\ProductFeeds\Shipping;
use Phil\ProductFeeds\Tests\Unit\Items;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TsvSerializer::class)]
#[CoversClass(Fields::class)]
final class TsvSerializerTest extends TestCase
{
    public function testTheHeaderNamesTheFieldsAndNothingFollowsTheLastRow(): void
    {
        $serializer = new TsvSerializer();

        self::assertSame(
            "id\ttitle\tdescription\tlink\timage_link\tadditional_image_link\tavailability\tavailability_date\tprice\tsale_price\tsale_price_effective_date\tcondition\tbrand\tgtin\tmpn\tidentifier_exists\titem_group_id\tcolor\tsize\tmaterial\tpattern\tproduct_type\tgoogle_product_category\tage_group\tgender\tshipping\tshipping_weight\tmultipack\tis_bundle\tcustom_label_0\tcustom_label_1\tcustom_label_2\tcustom_label_3\tcustom_label_4\n",
            $serializer->header(Items::channel()),
        );
        self::assertSame('', $serializer->footer());
    }

    public function testARowHoldsOneCellPerFieldEmptyOnesIncluded(): void
    {
        self::assertSame(
            "DICE-1\tDice set\t\thttps://shop.example/products/DICE-1\t\t\tin_stock\t\t25.00 CAD\t\t\tnew\t\t\t\tno\t\t\t\t\t\t".str_repeat("\t", 12)."\n",
            new TsvSerializer()->item(Items::item()),
        );
    }

    public function testLineBreaksAndTabsBecomeSpacesAndImagesAreJoinedByCommas(): void
    {
        $item = new FeedItem(
            'SHIRT',
            'Shirt',
            Items::url('https://shop.example/shirt'),
            Items::cad(3_000),
            Availability::Backorder,
            description: "Soft\tfluffy\r\nwarm\nand\rdry",
            additionalImageLinks: [Items::url('https://cdn.example/a,1.jpg'), Items::url('https://cdn.example/b.jpg')],
            availabilityDate: new DateTimeImmutable('2026-12-01 00:00:00+00:00'),
            salePrice: Items::cad(2_000),
            salePeriod: new SalePeriod(new DateTimeImmutable('2026-11-27 00:00:00+00:00'), new DateTimeImmutable('2026-12-01 00:00:00+00:00')),
            mpn: 'SH-1',
        );

        $cells = explode("\t", rtrim(new TsvSerializer()->item($item), "\n"));

        self::assertCount(\count(Fields::NAMES), $cells);
        self::assertSame('Soft fluffy warm and dry', $cells[2]);
        self::assertSame('https://cdn.example/a%2C1.jpg,https://cdn.example/b.jpg', $cells[5]);
        self::assertSame(['backorder', '2026-12-01T00:00:00+00:00', '30.00 CAD', '20.00 CAD', '2026-11-27T00:00:00+00:00/2026-12-01T00:00:00+00:00'], \array_slice($cells, 6, 5));
        self::assertSame(['SH-1', ''], \array_slice($cells, 14, 2));
    }

    public function testShippingCostsAreOneValueAndTheOtherFieldsTheirOwnColumns(): void
    {
        $item = new FeedItem(
            'SHIRT',
            'Shirt',
            Items::url('https://shop.example/shirt'),
            Items::cad(3_000),
            Availability::InStock,
            googleProductCategory: '212',
            ageGroup: AgeGroup::Kids,
            gender: Gender::Female,
            shipping: [new Shipping('CA', Items::cad(500)), new Shipping('US', Money::of(900, Currency::of('USD')), 'Express')],
            shippingWeight: 1_500,
            multipack: 6,
            isBundle: true,
            customLabels: ['Sale', '', 'Autumn'],
        );

        $cells = explode("\t", rtrim(new TsvSerializer()->item($item), "\n"));

        self::assertSame(
            ['212', 'kids', 'female', 'CA:::5.00 CAD,US::Express:9.00 USD', '1500 g', '6', 'yes', 'Sale', '', 'Autumn', '', ''],
            \array_slice($cells, 22),
        );
    }
}
