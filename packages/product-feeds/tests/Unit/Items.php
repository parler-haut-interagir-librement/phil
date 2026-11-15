<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit;

use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ProductFeeds\Availability;
use Phil\ProductFeeds\Contract\ProductSource;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedItem;
use Phil\Url\Url;

/**
 * Items, channels and sources for the tests.
 */
final class Items
{
    public static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    public static function url(string $url): Url
    {
        return Url::fromString($url);
    }

    public static function item(string $id = 'DICE-1', string $title = 'Dice set'): FeedItem
    {
        return new FeedItem($id, $title, self::url('https://shop.example/products/'.$id), self::cad(2_500), Availability::InStock);
    }

    public static function channel(): FeedChannel
    {
        return new FeedChannel('The Dice Shop', self::url('https://shop.example/'), 'Dice & games');
    }

    /**
     * @param iterable<FeedItem> $items
     */
    public static function source(iterable $items): ProductSource
    {
        return new readonly class($items) implements ProductSource {
            /** @param iterable<FeedItem> $items */
            public function __construct(private iterable $items)
            {
            }

            #[Override]
            public function items(): iterable
            {
                return $this->items;
            }
        };
    }
}
