<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\Money\Money;

/**
 * What the customer display shows of the cart: the lines, the totals and what is left to pay — never the customer's
 * name, a balance of points or anything about the person.
 */
final readonly class DisplayedCart
{
    /**
     * @param list<array{description: string, quantity: int, unitPrice: Money, subtotal: Money}> $lines
     */
    public function __construct(
        public string $orderNumber,
        public array $lines,
        public Money $items,
        public Money $discounts,
        public Money $tax,
        public bool $taxIncluded,
        public Money $tip,
        public Money $total,
        public Money $paid,
        public Money $left,
    ) {
    }

    /**
     * The cart as JSON-ready data, amounts in minor units with their currency: what the application pushes to the
     * display, through Mercure or any other channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $money = static fn (Money $amount): array => ['amount' => $amount->minorUnits, 'currency' => $amount->currency->code];

        return [
            'order' => $this->orderNumber,
            'lines' => array_map(static fn (array $line): array => [
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $money($line['unitPrice']),
                'subtotal' => $money($line['subtotal']),
            ], $this->lines),
            'items' => $money($this->items),
            'discounts' => $money($this->discounts),
            'tax' => $money($this->tax),
            'tax_included' => $this->taxIncluded,
            'tip' => $money($this->tip),
            'total' => $money($this->total),
            'paid' => $money($this->paid),
            'left' => $money($this->left),
        ];
    }
}
