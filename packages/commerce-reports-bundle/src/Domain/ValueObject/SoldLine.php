<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;
use Phil\Money\Money;

/**
 * A line sold in a period, as the sales reports read it: what was sold, where, by whom, how many, and for how much.
 *
 * The gross amount is the line's price times its quantity, before its discounts and without taxes or tips; the
 * discounts are written as a positive amount, in the gross amount's currency. A dimension the module does not know —
 * a sale without a cashier, a product without a category — is null.
 */
final readonly class SoldLine
{
    /**
     * @throws InvalidFigure when a text is empty or longer than 200 characters, the quantity is out of 1 to a billion,
     *                       an amount is negative, or the discounts are in another currency than the gross amount
     */
    public function __construct(
        public string $sku,
        public string $product,
        public ?string $category,
        public ?string $channel,
        public ?string $location,
        public ?string $cashier,
        public int $quantity,
        public Money $gross,
        public Money $discounts,
        public DateTimeImmutable $soldAt,
    ) {
        Text::required('SKU', $sku);
        Text::required('product', $product);
        Text::optional('category', $category);
        Text::optional('channel', $channel);
        Text::optional('location', $location);
        Text::optional('cashier', $cashier);
        Units::sold($quantity);

        if ($gross->isNegative() || $discounts->isNegative()) {
            throw InvalidFigure::because(\sprintf('the gross amount and the discounts of the SKU "%s" are not negative', $sku));
        }

        if (!$gross->currency->equals($discounts->currency)) {
            throw InvalidFigure::because(\sprintf('the gross amount and the discounts of the SKU "%s" are in one currency', $sku));
        }
    }
}
