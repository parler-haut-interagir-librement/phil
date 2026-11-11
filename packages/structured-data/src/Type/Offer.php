<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use DateTimeInterface;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * An `Offer`: a price in a currency — the price as a text with a dot, such as "19.99", so that no rounding of a float
 * changes it —, whether it can be had, where, until when and from whom.
 */
final class Offer
{
    /**
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string|int|float $price,
        string $priceCurrency,
        ?string $id = null,
        ?ItemAvailability $availability = null,
        ?string $url = null,
        DateTimeInterface|string|null $priceValidUntil = null,
        Node|Reference|null $seller = null,
        array $properties = [],
    ): Node {
        return Node::of('Offer', Properties::merge($properties, [
            'price' => $price,
            'priceCurrency' => $priceCurrency,
            'availability' => $availability,
            'url' => $url,
            'priceValidUntil' => $priceValidUntil,
            'seller' => $seller,
        ]), $id);
    }
}
