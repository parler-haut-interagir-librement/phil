<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use Phil\Money\Money;
use Phil\ProductFeeds\Exception\InvalidFeedItem;

/**
 * What delivering an item to a country costs, by one service or by any: the `shipping` field of an item, which a
 * reader shows beside the price. An item has one per country and service it ships by, and none when the reader holds
 * the store's shipping rates in its own settings.
 *
 * The country is an ISO 3166-1 alpha-2 code, in capitals; the price is not negative and may be zero; the service's
 * name holds 100 characters at most, neither a colon nor a comma, which separate the shipping values of a TSV file.
 */
final readonly class Shipping
{
    public const int MAX_SERVICE = 100;

    public ?string $service;

    public function __construct(
        public string $country,
        public Money $price,
        ?string $service = null,
    ) {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
            throw InvalidFeedItem::because(\sprintf('"%s" is not a country: two capital letters of ISO 3166-1.', $country));
        }

        if ($price->isNegative()) {
            throw InvalidFeedItem::because(\sprintf('The shipping to %s has a negative price.', $country));
        }

        $service = Text::optional($service, 'shipping service to '.$country, self::MAX_SERVICE);

        if (null !== $service && false !== strpbrk($service, ':,')) {
            throw InvalidFeedItem::because(\sprintf('The shipping service to %s holds neither a colon nor a comma.', $country));
        }

        $this->service = $service;
    }
}
