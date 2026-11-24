<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;

/**
 * Goods that came in at one location under one reference — a delivery note, a consignment slip —, optionally against a
 * purchase order of `phil/commerce-logistics-bundle`.
 */
final readonly class Receipt
{
    /** The most lines one receipt holds: a receipt is read and written in one request. */
    public const int MAX_LINES = 500;

    /**
     * @param non-empty-list<ReceiptLine> $lines
     */
    private function __construct(
        public string $reference,
        public string $location,
        public array $lines,
        public ?string $purchaseOrder,
    ) {
    }

    /**
     * @param list<ReceiptLine> $lines
     */
    public static function of(string $reference, string $location, array $lines, ?string $purchaseOrder = null): self
    {
        if ([] === $lines || \count($lines) > self::MAX_LINES) {
            throw InvalidValue::because(\sprintf('A receipt holds 1 to %d lines; %d were given.', self::MAX_LINES, \count($lines)));
        }

        return new self(
            InvalidValue::checkIdentifier($reference, 'reference'),
            InvalidValue::checkIdentifier($location, 'location'),
            $lines,
            null === $purchaseOrder ? null : InvalidValue::checkIdentifier($purchaseOrder, 'purchase order'),
        );
    }

    /**
     * The units of each SKU over every owner, in the order the lines name them.
     *
     * @return array<int|string, int> SKU → units; a numeric SKU is an integer key, as PHP makes it
     */
    public function quantities(): array
    {
        $quantities = [];

        foreach ($this->lines as $line) {
            $quantities[$line->sku] = ($quantities[$line->sku] ?? 0) + $line->quantity;
        }

        return $quantities;
    }
}
