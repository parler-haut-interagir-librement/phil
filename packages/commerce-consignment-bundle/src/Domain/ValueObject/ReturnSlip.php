<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\Money\Money;

/**
 * Units a consignor got back, or the shop bought from them, at one location under one reference, layer by layer.
 */
final readonly class ReturnSlip
{
    /**
     * @param list<SlipLine> $lines
     */
    public function __construct(
        public string $reference,
        public Owner $consignor,
        public string $location,
        public array $lines,
    ) {
    }

    public function quantity(): int
    {
        return array_sum(array_map(static fn (SlipLine $line): int => $line->quantity, $this->lines));
    }

    /**
     * What the shop owes the consignor for a buy-out; null for units given back.
     */
    public function owed(): ?Money
    {
        $owed = null;

        foreach ($this->lines as $line) {
            if ($line->owed instanceof Money) {
                $owed = $owed?->add($line->owed) ?? $line->owed;
            }
        }

        return $owed;
    }
}
