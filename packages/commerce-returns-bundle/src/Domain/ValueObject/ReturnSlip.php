<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;

/**
 * The slip a customer puts in the parcel of an authorized return: its number, the return and the order it names, the
 * lines coming back with their description, units and reason, and the label's tracking number when there is one.
 */
final readonly class ReturnSlip
{
    /**
     * @param list<array{line: string, description: string, quantity: int, reason: ReturnReason}> $lines
     */
    public function __construct(
        public string $number,
        public string $returnId,
        public string $orderNumber,
        public DateTimeImmutable $printedAt,
        public array $lines,
        public ?ReturnLabel $label,
    ) {
    }
}
