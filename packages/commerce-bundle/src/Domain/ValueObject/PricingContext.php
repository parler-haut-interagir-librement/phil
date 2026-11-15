<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a price may depend on: the quantity on the line, the moment of the sale, the buyer's customer group and the
 * channel that sells. A `UnitPriceResolver` reads what it needs and ignores the rest; the names of groups and
 * channels are the application's.
 */
final readonly class PricingContext
{
    private function __construct(
        public int $quantity,
        public DateTimeImmutable $at,
        public ?string $customerGroup,
        public ?string $channel,
    ) {
    }

    public static function of(int $quantity, DateTimeImmutable $at, ?string $customerGroup = null, ?string $channel = null): self
    {
        if ($quantity < 1) {
            throw InvalidValue::because(\sprintf('A price is asked for at least one unit; %d is not.', $quantity));
        }

        if ('' === $customerGroup || '' === $channel) {
            throw InvalidValue::because('A customer group or a channel, when given, has a name.');
        }

        return new self($quantity, $at, $customerGroup, $channel);
    }
}
