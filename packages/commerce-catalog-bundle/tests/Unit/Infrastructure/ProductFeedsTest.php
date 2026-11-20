<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\Channel\Offer;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceCatalogBundle\Application\ListedProduct;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\CatalogProductSource;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\FeedDefinition;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\ProductFeeds;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeed;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeedCommand;
use Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds\PublishProductFeedHandler;
use Phil\CommerceCatalogBundle\Tests\Unit\Support\InMemoryListing;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\Condition;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedFormat;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\FeedPublisher;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(CatalogProductSource::class)]
#[CoversClass(FeedDefinition::class)]
#[CoversClass(ProductFeeds::class)]
#[CoversClass(PublishProductFeed::class)]
#[CoversClass(PublishProductFeedHandler::class)]
#[CoversClass(PublishProductFeedCommand::class)]
final class ProductFeedsTest extends TestCase
{
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $logged = [];

    private string $directory = '';

    #[Override]
    protected function setUp(): void
    {
        $directory = sys_get_temp_dir().'/phil-catalog-feeds-'.bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directory = (string) realpath($directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = scandir($this->directory);
        self::assertIsArray($files);

        foreach ($files as $file) {
            if (is_file($this->directory.'/'.$file)) {
                unlink($this->directory.'/'.$file);
            }
        }

        rmdir($this->directory);
    }

    public function testAProductBecomesAnItemOfTheFeed(): void
    {
        $dice = $this->listed('DICE', attributes: [
            'brand' => 'Gnome', 'gtin' => '4006381333931', 'mpn' => 'D-1', 'description' => 'Six dice', 'condition' => 'used',
            'colour' => 'Red', 'material' => 'Resin', 'pattern' => 'Swirl', 'product_type' => 'Games > Dice', 'availability' => 'out_of_stock',
        ]);
        [$item] = $this->items($this->definition(attributes: ['color' => 'colour']), [$dice]);

        self::assertSame(['DICE', 'Dice', 'https://shop.example/products/DICE', 1_000, Availability::OutOfStock], [$item->id, $item->title, $item->link->toString(), $item->price->minorUnits, $item->availability]);
        self::assertSame(['Six dice', 'Gnome', '4006381333931', 'D-1', 'Red', 'Resin', 'Swirl', 'Games > Dice'], [$item->description, $item->brand, $item->gtin, $item->mpn, $item->color, $item->material, $item->pattern, $item->productType]);
        self::assertSame(Condition::Used, $item->condition);
        self::assertSame('https://cdn.example/DICE.jpg', $item->imageLink?->toString());
        self::assertNull($item->itemGroupId);
        self::assertNull($item->size);
        self::assertNull($item->salePrice);
    }

    public function testAVariantTakesItsAxesAndItsFamilyAsItsGroup(): void
    {
        $medium = $this->listed('SHIRT-M', attributes: ['size' => 'L', 'condition' => 'broken', 'image' => 'https://img.example/shirt.png'], family: 'SHIRT', options: ['size' => 'M']);
        [$item] = $this->items($this->definition(productLink: 'https://shop.example/{family}?variant={sku}'), [$medium]);

        self::assertSame('SHIRT', $item->itemGroupId);
        self::assertSame('M', $item->size);
        self::assertSame(Condition::New, $item->condition);
        self::assertSame('https://shop.example/SHIRT?variant=SHIRT-M', $item->link->toString());
        self::assertSame('https://img.example/shirt.png', $item->imageLink?->toString());
        self::assertSame(Availability::InStock, $item->availability);
    }

    public function testWithoutAnImageTemplateNorAttributeTheItemHasNoImage(): void
    {
        [$item] = $this->items($this->definition(imageLink: null), [$this->listed('DICE')]);

        self::assertNull($item->imageLink);
    }

    public function testAProductListedWithoutItsPriceIsLeftOut(): void
    {
        $items = $this->items($this->definition(), [
            $this->listed('A', offer: Offer::of(Visibility::Listed, false, false)),
            $this->listed('B'),
        ]);

        self::assertSame(['B'], array_map(static fn (FeedItem $item): string => $item->id, $items));
    }

    public function testAProductNoOrderMayHoldIsOutOfStockAndADatedAvailabilityToo(): void
    {
        $items = $this->items($this->definition(), [
            $this->listed('A', offer: Offer::of(Visibility::Listed, false)),
            $this->listed('B', attributes: ['availability' => 'preorder']),
            $this->listed('C', attributes: ['availability' => 'backorder']),
            $this->listed('D', attributes: ['availability' => 'in stock']),
        ]);

        self::assertSame([Availability::OutOfStock, Availability::OutOfStock, Availability::OutOfStock, Availability::InStock], array_map(static fn (FeedItem $item): Availability => $item->availability, $items));
    }

    public function testThePriceBooksGiveTheSalePriceWhenLower(): void
    {
        $asked = [];
        $prices = new class($asked) implements UnitPriceResolver {
            /** @param list<array{string, PricingContext}> $asked */
            public function __construct(public array &$asked)
            {
            }

            #[Override]
            public function priceFor(Sellable $sellable, PricingContext $context): ?Money
            {
                $this->asked[] = [$sellable->reference->value().' '.$sellable->description.' '.$sellable->unitPrice->minorUnits.' '.$sellable->taxCategory, $context];

                return match ($sellable->reference->id) {
                    'LOWER' => Money::of(999, Currency::of('CAD')),
                    'EQUAL' => Money::of(1_000, Currency::of('CAD')),
                    'EURO' => Money::of(1, Currency::of('EUR')),
                    default => null,
                };
            }
        };
        $at = new DateTimeImmutable('2026-11-27 12:00:00');
        $definition = $this->definition(salesChannel: 'web');
        $items = iterator_to_array(new CatalogProductSource(new InMemoryListing($this->listed('EQUAL'), $this->listed('EURO'), $this->listed('LOWER'), $this->listed('NONE')), $definition, $at, $prices)->items(), false);

        self::assertSame([null, null, 999, null], array_map(static fn (FeedItem $item): ?int => $item->salePrice?->minorUnits, $items));
        self::assertSame('product:EQUAL Equal 1000 goods', $asked[0][0]);
        self::assertSame([1, $at, null, 'web'], [$asked[0][1]->quantity, $asked[0][1]->at, $asked[0][1]->customerGroup, $asked[0][1]->channel]);
    }

    public function testAProductTheFeedCannotCarryIsLeftOutAndLogged(): void
    {
        $items = $this->items($this->definition(), [
            $this->listed('A', attributes: ['gtin' => '123']),
            $this->listed('B', attributes: ['image' => 'ftp://example.com/b.jpg']),
            $this->listed('C'),
        ]);

        self::assertSame(['C'], array_map(static fn (FeedItem $item): string => $item->id, $items));
        self::assertSame(['warning', 'The product {sku} is left out of the feed {feed}: {reason}'], \array_slice($this->logged[0], 0, 2));
        self::assertSame(['sku' => 'A', 'feed' => 'google', 'reason' => '"123" is not a GTIN: 8, 12, 13 or 14 digits, not all zeros, the last one checking the others.'], $this->logged[0][2]);
        self::assertSame('B', $this->logged[1][2]['sku']);
        self::assertCount(2, $this->logged);
    }

    public function testAProductTheFeedCannotCarryIsLeftOutWithoutALoggerToo(): void
    {
        $source = new CatalogProductSource(new InMemoryListing($this->listed('A', attributes: ['gtin' => '123']), $this->listed('B')), $this->definition(), new DateTimeImmutable());

        self::assertSame(['B'], array_map(static fn (FeedItem $item): string => $item->id, iterator_to_array($source->items(), false)));
    }

    public function testTheAddressesAreOffLocalhostUnlessTheFeedAllowsIt(): void
    {
        $listing = new InMemoryListing($this->listed('A'));
        $link = 'http://localhost/products/{sku}';

        self::assertSame([], iterator_to_array(new CatalogProductSource($listing, $this->definition(productLink: $link), new DateTimeImmutable())->items(), false));

        $allowed = new FeedDefinition('google', $this->directory.'/google.xml', FeedFormat::Xml, $this->channel(), $link, allowLocalhost: true);
        self::assertCount(1, iterator_to_array(new CatalogProductSource($listing, $allowed, new DateTimeImmutable())->items(), false));
        self::assertFalse(new FeedDefinition('google', 'f.xml', FeedFormat::Xml, $this->channel(), $link)->allowLocalhost);
    }

    public function testTheFeedIsPricedAtTheTimeOfTheClock(): void
    {
        $at = new DateTimeImmutable('2026-11-27 12:00:00');
        $clock = new readonly class($at) implements ClockInterface {
            public function __construct(private DateTimeImmutable $at)
            {
            }

            #[Override]
            public function now(): DateTimeImmutable
            {
                return $this->at;
            }
        };
        $asked = [];
        $prices = new class($asked) implements UnitPriceResolver {
            /** @param list<DateTimeImmutable> $asked */
            public function __construct(public array &$asked)
            {
            }

            #[Override]
            public function priceFor(Sellable $sellable, PricingContext $context): ?Money
            {
                $this->asked[] = $context->at;

                return null;
            }
        };

        new ProductFeeds([$this->definition()], new InMemoryListing($this->listed('DICE')), $clock, new FeedPublisher(), $prices)->publish('google');

        self::assertSame([$at], $asked);
    }

    public function testTheCatalogueIsReadPageByPageWithoutFamilies(): void
    {
        $products = array_map(fn (int $i): ListedProduct => $this->listed(\sprintf('P%03d', $i)), range(1, CatalogProductSource::PAGE + 1));
        $listing = new InMemoryListing(...$products);

        $items = iterator_to_array(new CatalogProductSource($listing, $this->definition(salesChannel: 'web', locale: 'fr'), new DateTimeImmutable())->items(), false);

        self::assertCount(CatalogProductSource::PAGE + 1, $items);
        self::assertSame([['web', null, 200, 'fr', false], ['web', 'P200', 200, 'fr', false]], $listing->asked);
    }

    public function testADefinitionNamesItsProductAndReadsKnownFields(): void
    {
        $definition = $this->definition(attributes: ['color' => 'colour', 'gtin' => 'ean']);

        self::assertSame('colour', $definition->attributes['color']);
        self::assertSame('ean', $definition->attributes['gtin']);
        self::assertSame('size', $definition->attributes['size']);
        self::assertCount(\count(FeedDefinition::FIELDS), $definition->attributes);
        self::assertSame('https://x.example/A%2FB/A%2FB', FeedDefinition::address('https://x.example/{sku}/{family}', 'A/B', null));
        self::assertSame('https://x.example/S%20M/SHIRT', FeedDefinition::address('https://x.example/{sku}/{family}', 'S M', 'SHIRT'));
        self::assertSame('https://x.example/{family}', $this->definition(productLink: 'https://x.example/{family}')->productLink);

        try {
            $this->definition(productLink: 'https://x.example/products');
            self::fail('A link that names no product was taken.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('The product link of the feed google names {sku} or {family}.', $invalidArgumentException->getMessage());
        }

        self::assertSame(3600, $this->definition()->maxAge);
        self::assertSame(0, new FeedDefinition('zero', 'f.xml', FeedFormat::Xml, $this->channel(), 'https://x.example/{sku}', maxAge: 0)->maxAge);

        try {
            new FeedDefinition('google', 'f.xml', FeedFormat::Xml, $this->channel(), 'https://x.example/{sku}', maxAge: -1);
            self::fail('A negative age was taken.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('The feed google is kept -1 seconds: zero or more.', $invalidArgumentException->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The feed google reads no field colour, ean; its fields are description, image, brand, gtin, mpn, condition, availability, color, size, material, pattern, product_type.');

        $this->definition(attributes: ['colour' => 'c', 'ean' => 'e']);
    }

    public function testTheFeedsArePublishedByName(): void
    {
        $clock = new class implements ClockInterface {
            #[Override]
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-27 12:00:00');
            }
        };
        $feeds = new ProductFeeds([$this->definition(), $this->definition('meta', FeedFormat::Tsv)], new InMemoryListing($this->listed('DICE')), $clock, new FeedPublisher(), null, $this->logger());

        self::assertSame(['google', 'meta'], $feeds->names());
        self::assertSame('meta', $feeds->definition('meta')?->name);
        self::assertNull($feeds->definition('other'));

        $feed = $feeds->publish('meta');
        self::assertSame([$this->directory.'/meta.tsv', 1], [$feed->path, $feed->items]);
        self::assertStringStartsWith("id\ttitle", (string) file_get_contents($feed->path));
        self::assertSame(['info', 'The feed {feed} is published: {items} items, {bytes} bytes.', ['feed' => 'meta', 'items' => 1, 'bytes' => $feed->bytes]], $this->logged[0]);

        new PublishProductFeedHandler($feeds)(new PublishProductFeed('google'));
        self::assertStringContainsString('<g:id>DICE</g:id>', (string) file_get_contents($this->directory.'/google.xml'));

        try {
            $feeds->publish('other');
            self::fail('An unknown feed was published.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('No feed is named "other"; the feeds are: google, meta.', $invalidArgumentException->getMessage());
        }

        $this->expectExceptionMessageIs('No feed is named "google"; the feeds are: none.');

        new ProductFeeds([], new InMemoryListing())->publish('google');
    }

    public function testWithoutAClockTheFeedIsPricedNow(): void
    {
        $feed = new ProductFeeds([$this->definition()], new InMemoryListing($this->listed('DICE')))->publish('google');

        self::assertSame(1, $feed->items);
    }

    public function testTheCommandPublishesEveryFeedOrThoseNamed(): void
    {
        $feeds = new ProductFeeds([$this->definition(), $this->definition('meta', FeedFormat::Tsv)], new InMemoryListing($this->listed('DICE')));
        $command = new PublishProductFeedCommand($feeds);

        [$status, $output] = $this->execute($command, []);
        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('google: 1 items,', $output);
        self::assertStringContainsString('meta: 1 items,', $output);
        self::assertFileExists($this->directory.'/meta.tsv');

        [$status, $output] = $this->execute($command, ['meta', 'other']);
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('meta: 1 items,', $output);
        self::assertStringContainsString('other: No feed is named "other"', $output);
        self::assertStringNotContainsString('google:', $output);

        [$status, $output] = $this->execute(new PublishProductFeedCommand(new ProductFeeds([], new InMemoryListing())), []);
        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('No feed is declared under phil_commerce_catalog.product_feeds.', $output);
    }

    public function testTheCommandReportsAFeedThatCannotBeWritten(): void
    {
        $definition = new FeedDefinition('lost', $this->directory.'/missing/lost.xml', FeedFormat::Xml, $this->channel(), 'https://shop.example/{sku}');

        [$status, $output] = $this->execute(new PublishProductFeedCommand(new ProductFeeds([$definition], new InMemoryListing())), []);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('lost: The feed is written in an existing, writable directory;', $output);
    }

    /**
     * @param list<string> $feeds
     *
     * @return array{int, string}
     */
    private function execute(PublishProductFeedCommand $command, array $feeds): array
    {
        $output = new BufferedOutput();
        $status = $command(new SymfonyStyle(new ArrayInput([]), $output), $feeds);

        return [$status, (string) preg_replace('/\s+/', ' ', $output->fetch())];
    }

    /**
     * @param list<ListedProduct> $products
     *
     * @return list<FeedItem>
     */
    private function items(FeedDefinition $definition, array $products): array
    {
        return iterator_to_array(new CatalogProductSource(new InMemoryListing(...$products), $definition, new DateTimeImmutable(), null, $this->logger())->items(), false);
    }

    /**
     * @param array<string, string> $attributes
     */
    private function definition(
        string $name = 'google',
        FeedFormat $format = FeedFormat::Xml,
        string $productLink = 'https://shop.example/products/{sku}',
        ?string $imageLink = 'https://cdn.example/{sku}.jpg',
        ?string $salesChannel = null,
        ?string $locale = null,
        array $attributes = [],
    ): FeedDefinition {
        return new FeedDefinition($name, $this->directory.'/'.$name.'.'.$format->extension(), $format, $this->channel(), $productLink, $imageLink, $salesChannel, $locale, $attributes);
    }

    private function channel(): FeedChannel
    {
        return new FeedChannel('Shop', Url::fromString('https://shop.example/'), 'Dice');
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, string> $options
     */
    private function listed(string $sku, ?Offer $offer = null, array $attributes = [], ?string $family = null, array $options = []): ListedProduct
    {
        return new ListedProduct($sku, ProductKind::Physical, ucfirst(strtolower($sku)), Money::of(1_000, Currency::of('CAD')), 'goods', $offer ?? Offer::full(), $attributes, $family, $options);
    }

    private function logger(): AbstractLogger
    {
        $record = function (string $level, string $message, array $context): void {
            /** @var array<string, mixed> $context */
            $this->logged[] = [$level, $message, $context];
        };

        return new class($record) extends AbstractLogger {
            /** @param Closure(string, string, array<mixed>): void $record */
            public function __construct(private readonly Closure $record)
            {
            }

            /**
             * @param array<mixed> $context
             */
            #[Override]
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                ($this->record)(\is_string($level) ? $level : '', (string) $message, $context);
            }
        };
    }
}
