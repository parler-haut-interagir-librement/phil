<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * What the shop and a consignor agreed for units left on consignment: either the consignor's share of each unit sold,
 * a fixed amount, or the shop's commission on the price paid, a rate in parts per million — 300 000 for 30 % —, the
 * unit of `phil/pricing`'s rates. An integer rate never rounds a fraction away before the price is known.
 */
final readonly class ConsignmentTerms
{
    /** The whole price: a commission of 1 000 000 parts per million keeps all of it. */
    public const int WHOLE = 1_000_000;

    private function __construct(
        public ?Money $share,
        public ?int $commissionPpm,
    ) {
    }

    /**
     * The consignor receives this amount for each unit sold, whatever the price paid.
     */
    public static function share(Money $perUnit): self
    {
        if ($perUnit->isNegative()) {
            throw InvalidValue::because(\sprintf("A consignor's share is zero or more; %s is not.", $perUnit->toDecimal()));
        }

        return new self($perUnit, null);
    }

    /**
     * The shop keeps this part of the price paid, in parts per million; the consignor receives the rest.
     */
    public static function commission(int $ppm): self
    {
        if ($ppm < 0 || $ppm > self::WHOLE) {
            throw InvalidValue::because(\sprintf('A commission is 0 to 1 000 000 parts per million; %d is not.', $ppm));
        }

        return new self(null, $ppm);
    }

    public function isShare(): bool
    {
        return $this->share instanceof Money;
    }
}
