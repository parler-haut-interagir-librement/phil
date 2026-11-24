<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;
use Phil\Money\Money;

/**
 * A line returned in a period, as the sales reports read it: what came back, through which channel and location, who
 * took it back, how many, and the amount given back for it, without taxes, as a positive amount.
 *
 * A return counts in the period it was received, whatever the period of the sale: a period can give back more than it
 * sold.
 */
final readonly class ReturnedLine
{
    /**
     * @throws InvalidFigure when a text is empty or longer than 200 characters, the quantity is out of 1 to a billion,
     *                       or the amount is negative
     */
    public function __construct(
        public string $sku,
        public string $product,
        public ?string $category,
        public ?string $channel,
        public ?string $location,
        public ?string $cashier,
        public int $quantity,
        public Money $amount,
        public DateTimeImmutable $returnedAt,
    ) {
        Text::required('SKU', $sku);
        Text::required('product', $product);
        Text::optional('category', $category);
        Text::optional('channel', $channel);
        Text::optional('location', $location);
        Text::optional('cashier', $cashier);
        Units::sold($quantity);

        if ($amount->isNegative()) {
            throw InvalidFigure::because(\sprintf('the amount returned for the SKU "%s" is not negative', $sku));
        }
    }
}
