<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Benchmark;

use Generator;
use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\Contract\ProductSource;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedFormat;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\FeedPublisher;
use Phil\Url\Url;

/**
 * A scheduled feed writes the whole catalogue: the time per item, and a memory that stays flat whatever its length.
 *
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 */
final class FeedPublisherBench
{
    private string $path = '';

    private ProductSource $source;

    private readonly FeedChannel $channel;

    public function __construct()
    {
        $this->channel = new FeedChannel('Shop', Url::fromString('https://shop.example/'), 'Products');
        $this->source = $this->source(0);
    }

    /**
     * @param array{items: int, format: string} $params
     */
    public function setUp(array $params): void
    {
        $this->path = sys_get_temp_dir().'/phil-product-feeds-bench.'.$params['format'];
        $this->source = $this->source($params['items']);
    }

    public function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @param array{items: int, format: string} $params
     *
     * @ParamProviders("catalogues")
     *
     * @Revs(5)
     */
    public function benchPublish(array $params): void
    {
        new FeedPublisher()->publish($this->source, $this->channel, FeedFormat::from($params['format'])->serializer(), $this->path);
    }

    /**
     * @return Generator<string, array{items: int, format: string}>
     */
    public function catalogues(): Generator
    {
        foreach (['xml', 'tsv'] as $format) {
            yield '1 000 items, '.$format => ['items' => 1_000, 'format' => $format];
            yield '10 000 items, '.$format => ['items' => 10_000, 'format' => $format];
        }
    }

    private function source(int $count): ProductSource
    {
        return new readonly class($count) implements ProductSource {
            public function __construct(private int $count)
            {
            }

            #[Override]
            public function items(): iterable
            {
                $price = Money::of(2_500, Currency::of('CAD'));

                for ($i = 0; $i < $this->count; ++$i) {
                    yield new FeedItem(
                        'SKU-'.$i,
                        'Dice set number '.$i,
                        Url::fromString('https://shop.example/products/SKU-'.$i),
                        $price,
                        Availability::InStock,
                        description: 'Six resin dice in a cotton bag, for every table.',
                        imageLink: Url::fromString('https://cdn.example/SKU-'.$i.'.jpg'),
                        brand: 'Gnome',
                        gtin: '4006381333931',
                        itemGroupId: 'DICE',
                        color: 'Red',
                    );
                }
            }
        };
    }
}
