<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit\Serializer;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Phil\ProductFeeds\AgeGroup;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\Condition;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\Gender;
use Phil\ProductFeeds\SalePeriod;
use Phil\ProductFeeds\Serializer\Fields;
use Phil\ProductFeeds\Serializer\RssSerializer;
use Phil\ProductFeeds\Shipping;
use Phil\ProductFeeds\Tests\Unit\Items;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RssSerializer::class)]
#[CoversClass(Fields::class)]
final class RssSerializerTest extends TestCase
{
    public function testTheChannelOpensTheFileAndTheFooterClosesIt(): void
    {
        $serializer = new RssSerializer();

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">'."\n"
            .'<channel>'."\n"
            .'<title>The Dice Shop</title>'."\n"
            .'<link>https://shop.example/</link>'."\n"
            .'<description>Dice &amp; games</description>'."\n",
            $serializer->header(Items::channel()),
        );
        self::assertSame('</channel>'."\n".'</rss>'."\n", $serializer->footer());
    }

    public function testAnItemWritesItsFieldsAndLeavesOutTheEmptyOnes(): void
    {
        self::assertSame(
            '<item>'."\n"
            .'<g:id>DICE-1</g:id>'."\n"
            .'<g:title>Dice &lt;6&gt; &quot;set&quot; &apos;red&apos;</g:title>'."\n"
            .'<g:link>https://shop.example/products/DICE-1</g:link>'."\n"
            .'<g:availability>in_stock</g:availability>'."\n"
            .'<g:price>25.00 CAD</g:price>'."\n"
            .'<g:condition>new</g:condition>'."\n"
            .'<g:identifier_exists>no</g:identifier_exists>'."\n"
            .'</item>'."\n",
            new RssSerializer()->item(Items::item(title: 'Dice <6> "set" \'red\'')),
        );
    }

    public function testEveryFieldIsWrittenInItsOrder(): void
    {
        $xml = new RssSerializer()->item($this->fullItem());

        self::assertSame(
            '<item>'."\n"
            .'<g:id>SHIRT-M</g:id>'."\n"
            .'<g:title>Shirt</g:title>'."\n"
            .'<g:description>Soft'."\n".'&amp; warm</g:description>'."\n"
            .'<g:link>https://shop.example/shirt?size=m&amp;c=red</g:link>'."\n"
            .'<g:image_link>https://cdn.example/shirt.jpg</g:image_link>'."\n"
            .'<g:additional_image_link>https://cdn.example/a,1.jpg</g:additional_image_link>'."\n"
            .'<g:additional_image_link>https://cdn.example/b.jpg</g:additional_image_link>'."\n"
            .'<g:availability>preorder</g:availability>'."\n"
            .'<g:availability_date>2026-12-01T00:00:00+00:00</g:availability_date>'."\n"
            .'<g:price>30.00 CAD</g:price>'."\n"
            .'<g:sale_price>24.99 CAD</g:sale_price>'."\n"
            .'<g:sale_price_effective_date>2026-11-27T00:00:00+00:00/2026-12-01T00:00:00+00:00</g:sale_price_effective_date>'."\n"
            .'<g:condition>refurbished</g:condition>'."\n"
            .'<g:brand>Gnome</g:brand>'."\n"
            .'<g:gtin>4006381333931</g:gtin>'."\n"
            .'<g:mpn>SH-1</g:mpn>'."\n"
            .'<g:item_group_id>SHIRT</g:item_group_id>'."\n"
            .'<g:color>Red</g:color>'."\n"
            .'<g:size>M</g:size>'."\n"
            .'<g:material>Cotton</g:material>'."\n"
            .'<g:pattern>Plain</g:pattern>'."\n"
            .'<g:product_type>Apparel &gt; Shirts</g:product_type>'."\n"
            .'<g:google_product_category>Apparel &amp; Accessories &gt; Clothing</g:google_product_category>'."\n"
            .'<g:age_group>adult</g:age_group>'."\n"
            .'<g:gender>male</g:gender>'."\n"
            .'<g:shipping><g:country>CA</g:country><g:price>5.00 CAD</g:price></g:shipping>'."\n"
            .'<g:shipping><g:country>CA</g:country><g:service>Express &amp; tracked</g:service><g:price>12.50 CAD</g:price></g:shipping>'."\n"
            .'<g:shipping_weight>250 g</g:shipping_weight>'."\n"
            .'<g:multipack>2</g:multipack>'."\n"
            .'<g:is_bundle>yes</g:is_bundle>'."\n"
            .'<g:custom_label_0>Sale</g:custom_label_0>'."\n"
            .'<g:custom_label_4>Q&lt;4&gt;</g:custom_label_4>'."\n"
            .'</item>'."\n",
            $xml,
        );
    }

    public function testTheWholeFileIsWellFormedRss(): void
    {
        $serializer = new RssSerializer();
        $document = new DOMDocument();

        self::assertTrue($document->loadXML('<?xml version="1.0" encoding="UTF-8"?>'.substr($serializer->header(Items::channel()), 38).$serializer->item($this->fullItem()).$serializer->item(Items::item('DICE-2', "Tricky ]]> \u{1F3B2}")).$serializer->footer()));

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('g', RssSerializer::PRODUCT_NAMESPACE);

        self::assertSame('2.0', $document->documentElement?->getAttribute('version'));
        self::assertSame(2.0, $xpath->evaluate('count(/rss/channel/item)'));
        self::assertSame("Tricky ]]> \u{1F3B2}", $xpath->evaluate('string(/rss/channel/item[2]/g:title)'));
        self::assertSame('https://shop.example/shirt?size=m&c=red', $xpath->evaluate('string(/rss/channel/item[1]/g:link)'));
        self::assertSame('12.50 CAD', $xpath->evaluate('string(/rss/channel/item[1]/g:shipping[2]/g:price)'));
    }

    private function fullItem(): FeedItem
    {
        return new FeedItem(
            'SHIRT-M',
            'Shirt',
            Items::url('https://shop.example/shirt?size=m&c=red'),
            Items::cad(3_000),
            Availability::Preorder,
            description: "Soft\n& warm",
            imageLink: Items::url('https://cdn.example/shirt.jpg'),
            additionalImageLinks: [Items::url('https://cdn.example/a,1.jpg'), Items::url('https://cdn.example/b.jpg')],
            availabilityDate: new DateTimeImmutable('2026-12-01 00:00:00+00:00'),
            salePrice: Items::cad(2_499),
            salePeriod: new SalePeriod(new DateTimeImmutable('2026-11-27 00:00:00+00:00'), new DateTimeImmutable('2026-12-01 00:00:00+00:00')),
            condition: Condition::Refurbished,
            brand: 'Gnome',
            gtin: '4006381333931',
            mpn: 'SH-1',
            itemGroupId: 'SHIRT',
            color: 'Red',
            size: 'M',
            material: 'Cotton',
            pattern: 'Plain',
            productType: 'Apparel > Shirts',
            googleProductCategory: 'Apparel & Accessories > Clothing',
            ageGroup: AgeGroup::Adult,
            gender: Gender::Male,
            shipping: [new Shipping('CA', Items::cad(500)), new Shipping('CA', Items::cad(1_250), 'Express & tracked')],
            shippingWeight: 250,
            multipack: 2,
            isBundle: true,
            customLabels: ['Sale', '', ' ', '', 'Q<4>'],
        );
    }
}
