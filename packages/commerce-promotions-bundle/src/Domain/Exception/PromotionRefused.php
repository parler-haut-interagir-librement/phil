<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A change the promotions refuse.
 */
final class PromotionRefused extends DomainException implements DomainError
{
    public static function batch(int $count): self
    {
        return new self(\sprintf('A batch holds 1 to 10000 codes; %d does not.', $count));
    }

    public static function exhausted(string $promotion, ?string $code): self
    {
        return new self(null === $code
            ? \sprintf('The promotion %s reached its limit of uses before this order was counted.', $promotion)
            : \sprintf('The promotion %s or its code %s reached its limit of uses before this order was counted.', $promotion, $code));
    }

    public static function exhaustedForCustomer(string $promotion): self
    {
        return new self(\sprintf('The customer reached the limit of uses of the promotion %s before this order was counted.', $promotion));
    }

    public static function notPlaced(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: its promotions are counted once it is.', $orderNumber));
    }
}
