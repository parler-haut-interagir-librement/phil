<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\ValueObject;

use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Domain\Guard;

/**
 * One line of a receipt as it was issued: what was sold, how many, at what unit price, its amount — negative on a
 * credit note —, the taxes charged on it, and its options: those shown, and the labels of those still to draw.
 */
final readonly class ReceiptLine
{
    /** The most taxes, options or pending options a line holds. */
    public const int MAX_ITEMS = 20;

    /**
     * @param list<TaxAmount> $taxes
     * @param list<string>    $options
     * @param list<string>    $pending
     */
    private function __construct(
        public string $description,
        public int $quantity,
        public Money $unitPrice,
        public Money $amount,
        public array $taxes,
        public array $options,
        public array $pending,
    ) {
    }

    /**
     * @param list<TaxAmount> $taxes   the taxes of this line, in the order the receipt lists them
     * @param list<string>    $options each option as the receipt shows it, `Size: M`
     * @param list<string>    $pending the labels of the options drawn after payment
     */
    public static function of(string $description, int $quantity, Money $unitPrice, Money $amount, array $taxes = [], array $options = [], array $pending = []): self
    {
        Guard::text($description, 'The description of a line');

        if ($quantity < 1) {
            throw InvalidReceipt::because('A line holds at least one unit.');
        }

        Guard::currency($amount, $unitPrice->currency, 'The amount of a line');

        if (\count($taxes) > self::MAX_ITEMS) {
            throw InvalidReceipt::because(\sprintf('A line holds a list of at most %d taxes.', self::MAX_ITEMS));
        }

        foreach ($taxes as $tax) {
            Guard::currency($tax->amount, $unitPrice->currency, 'A tax of a line');
        }

        return new self(
            $description,
            $quantity,
            $unitPrice,
            $amount,
            $taxes,
            Guard::lines($options, 'The options of a line', self::MAX_ITEMS),
            Guard::lines($pending, 'The pending options of a line', self::MAX_ITEMS),
        );
    }
}
