<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Purchasing;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * One SKU of a purchase order: the units ordered and, when the caller gives them, what each unit costs and who owns
 * the units. The owner is the caller's own identifier — a consignor, a supplier, a department —, kept as it is given:
 * the logistics knows nothing of owners.
 */
final readonly class PurchaseLine
{
    private function __construct(
        public int $units,
        public ?Money $unitCost,
        public ?string $owner,
    ) {
    }

    /**
     * @param Money|null  $unitCost zero or more, in any currency
     * @param string|null $owner    1 to 64 characters, none of them a control character
     */
    public static function of(int $units, ?Money $unitCost = null, ?string $owner = null): self
    {
        if ($units < 1) {
            throw InvalidValue::because(\sprintf('A purchase order line orders one unit or more; %d is not.', $units));
        }

        if (true === $unitCost?->isNegative()) {
            throw InvalidValue::because(\sprintf('A unit cost is zero or more; %s %s is not.', $unitCost->toDecimal(), $unitCost->currency->code));
        }

        if (null !== $owner && ('' === trim($owner) || mb_strlen($owner) > 64 || 1 === preg_match('/[\x00-\x1F\x7F]/', $owner))) {
            throw InvalidValue::because('An owner has 1 to 64 characters, none of them a control character.');
        }

        return new self($units, $unitCost, $owner);
    }
}
