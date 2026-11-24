<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;

/**
 * The stock of a SKU over a period, across its locations: the units on hand when the period starts, the units received
 * during it, the units available now, and the moment of its last sale, null when it never sold.
 */
final readonly class StockFigure
{
    /**
     * @throws InvalidFigure when the SKU is empty or longer than 200 characters, a count is out of minus a billion to a
     *                       billion, or the units received are negative
     */
    public function __construct(
        public string $sku,
        public int $start,
        public int $received,
        public int $available,
        public ?DateTimeImmutable $lastSoldAt,
    ) {
        Text::required('SKU', $sku);
        Units::counted('starting stock', $start);
        Units::counted('stock available', $available);

        if ($received < 0 || $received > Units::MAX) {
            throw InvalidFigure::because(\sprintf('the units received of the SKU "%s" are from 0 to %d', $sku, Units::MAX));
        }
    }
}
